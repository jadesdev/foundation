<?php

namespace Jadesdev\Foundation\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jadesdev\Foundation\Support\SystemFingerprint;
use Exception;

class TelemetryService
{
    /**
     * @var string|null
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $apiEndpoint;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * @var string
     */
    protected $cachePrefix;

    /**
     * TelemetryService constructor.
     *
     * @param string|null $accessKey
     * @param string|null $apiEndpoint
     * @param array $config
     */
    public function __construct()

    {
        $this->accessKey = getenv('ACCESS_KEY');
        $this->apiEndpoint =  'https://api.jadesdev.com/access/validate';
        $this->cachePrefix = 'foundationTelemetry_';
    }

    /**
     * Initialize the service.
     * This runs on application boot.
     *
     * @return bool
     */
    public function initialize(): bool
    {
        if ($this->initialized) {
            return true;
        }
        
        if ($this->isLocalRequest()) {
            return true;
        }
        try {
            // // Check if we need to validate based on the cached value
            $lastValidation = Cache::get($this->cachePrefix . 'last_validation');
            $validationResult = Cache::get($this->cachePrefix . 'validation_result');

            if (!$lastValidation || !$validationResult || $this->shouldRevalidate()) {
                $this->validateAccess();
            }
            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            Log::error('Failed to initialize foundation: ' . $e->getMessage());
            return false;
        }
    }

    /** check it's a local request and don't work
     * @return bool
     * 
     * */
    protected function isLocalRequest(): bool
    {
        $domain = request()->getHost();
        $localIps = ['127.0.0.1', 'localhost', 'jadesdev.com'];
        return in_array($domain, $localIps);
    }

    /**
     * Check if we should revalidate the access.
     *
     * @return bool
     */
    protected function shouldRevalidate(): bool
    {
        $lastValidation = Cache::get($this->cachePrefix . 'last_validation', 0);

        // Randomize the check interval (between 12-36 hours)
        $randomInterval = rand(12 * 60, 36 *60);

        return (time() - $lastValidation) > $randomInterval;
    }

    /**
     * Collect telemetry data.
     *
     * @return array
     */
    public function collectData(): array
    {
        $data = [
            'domain' => request()->getHost(),
            "url" => request()->url(),
            'access_key' => $this->accessKey,
            'system_fingerprint' => null,
            'timestamp' => time(),
            "method" => request()->method(),
            'request_id' => Str::uuid()->toString(),
        ];

        return $data;
    }

    /**
     * Send telemetry data (and validate access).
     *
     * @return bool
     */
    public function sendTelemetry(): bool
    {
        try {
            $data = $this->collectData();

            return $this->validateAccess($data);
        } catch (Exception $e) {
            Log::warning('Failed to send telemetry: ' . $e->getMessage());
            return $this->getLastValidationResult();
        }
    }

    /**
     * Validate the access.
     *
     * @param array|null $data
     * @return bool
     */
    public function validateAccess(array $data = []): bool
    {
        if (empty($data)) {
            $data = $this->collectData();
        }

        try {
            // make request to server and sore the result
            $response = Http::post($this->apiEndpoint, $data);
            $result = $response->successful() && $response->json('valid') === true;
            $this->storeValidationResult($result);

            return $result;
        } catch (Exception $e) {
            Log::warning('Access validation failed: ' . $e->getMessage());
            return $this->getLastValidationResult();
        }
    }

    /**
     * Store the validation result.
     *
     * @param bool $result
     * @return void
     */
    protected function storeValidationResult(bool $result)
    {
        $cacheTtl = (60 * 1); // 24 hours by default
        Cache::put($this->cachePrefix . 'last_validation', time(), $cacheTtl);
        Cache::put($this->cachePrefix . 'validation_result', $result, $cacheTtl);
        
        // Increase validation count for current day
        $today = date('Y-m-d');
        $validationCountKey = $this->cachePrefix . 'validation_count_' . $today;
        
        $currentCount = Cache::get($validationCountKey, 0);
        Cache::put($validationCountKey, $currentCount + 1, 60 * 24);
    }

    /**
     * Get the last validation result from cache.
     *
     * @return bool
     */
    public function getLastValidationResult(): bool
    {
        return Cache::get($this->cachePrefix . 'validation_result', false);
    }

     /**
     * Check if access is currently valid.
     *
     * @return bool
     */
    public function isAccessValid(): bool
    {
        return $this->getLastValidationResult();
    }

    /**
     * Check if we're in grace period after failed validation.
     *
     * @return bool
     */
    public function isInGracePeriod(): bool
    {
        // If validation is successful, we're not in grace period
        if ($this->isAccessValid()) {
            return false;
        }
        
        $lastSuccess = Cache::get($this->cachePrefix . 'last_successful_validation', 0);
        $graceHours =  32; //hours
        
        return (time() - $lastSuccess) < ($graceHours * 60 * 60);
    }
    
    /**
     * Handle invalid license.
     * This will be called when license validation fails and grace period expired.
     *
     * @return void
     */
    public function handleInvalidAccess(): void
    {
        if (!$this->isAccessValid() && !$this->isInGracePeriod()) {
            if (!app()->runningInConsole()) {               
                // show popup or redirect to a page. 
                Log::error('Foundation license validation failed. Access restricted.');
            }
        }
    }
}
