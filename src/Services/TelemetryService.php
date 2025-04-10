<?php

namespace Jadesdev\Foundation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
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
        $this->accessKey = env('ACCESS_KEY');
        $this->apiEndpoint =  'https://license.jadesdev.com.ng/api/validate';
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
            return false;
        }
    }

    /** check it's a local request and don't work
     * @return bool
     * 
     * */
    protected function isLocalRequest(): bool
    {
        $ip = request()->ip();
        $host = request()->getHost();
        $localHosts = ['127.0.0.1', 'localhost', 'jadesdev.com', '::1'];
        return in_array($ip, $localHosts) || in_array($host, $localHosts);
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
        $randomInterval = rand(12 * 60, 36 * 60);

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
            $response = Http::post($this->apiEndpoint, $data);
            $result = $this->handleResponse($response);
            $this->storeValidationResult($result);
            return $result;
        } catch (Exception $e) {
            return $this->getLastValidationResult();
        }
    }

    protected function handleResponse($response){
        // do some secure check, key, encryption,etc
        $result = $response->successful() && $response->json('valid') === true;
        return $result;
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
        if ($this->isLocalRequest()) {
            return;
        }
        if (!$this->isAccessValid() && !$this->isInGracePeriod()) {
            if (!app()->runningInConsole()) {
                if (request()->wantsJson()) {
                    // For API requests, return an error response
                    abort(403, 'Foundation license validation failed. Please contact support.');
                } else {
                    $viewData = [
                        'domain' => request()->getHost(),
                        'days_elapsed' => $this->getDaysElapsedSinceLastSuccess(),
                        'support_url' => 'https://www.jadesdev.com.ng/support',
                        'access_key' => $this->accessKey,
                    ];

                    $content = view('foundation::access-invalid', $viewData)->render();
                    http_response_code(403);
                    echo $content;
                    exit;
                }
            }
        }
    }
    /**
     * Get the number of days elapsed since last successful validation
     *
     * @return int
     */
    protected function getDaysElapsedSinceLastSuccess(): int
    {
        $lastSuccess = Cache::get($this->cachePrefix . 'last_successful_validation', 0);
        if ($lastSuccess === 0) {
            return 999; // Never had a successful validation
        }

        $secondsElapsed = time() - $lastSuccess;
        return ceil($secondsElapsed / (24 * 60 * 60));
    }

    /**
     * Get the number of days remaining in the grace period
     * 
     * @return int
     */
    public function getGracePeriodDaysRemaining(): int
    {
        if ($this->isAccessValid()) {
            return 0; // Not in grace period
        }

        $lastSuccess = Cache::get($this->cachePrefix . 'last_successful_validation', 0);
        $graceHours = 72; // 3 days
        $secondsElapsed = time() - $lastSuccess;
        $secondsTotal = $graceHours * 60 * 60;
        $secondsRemaining = $secondsTotal - $secondsElapsed;

        return max(0, ceil($secondsRemaining / (24 * 60 * 60)));
    }
}
