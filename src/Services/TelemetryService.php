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
        $this->accessKey = '1234';
        $this->apiEndpoint =  'https://api.jadesdev.com/license/validate';
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

        try {
            // // Check if we need to validate based on the cached value
            // $lastValidation = Cache::get($this->cachePrefix . 'last_validation');
            // $validationResult = Cache::get($this->cachePrefix . 'validation_result');

            // if (!$lastValidation || !$validationResult || $this->shouldRevalidate()) {
            //     $this->validateAccess();
            // }
            $this->validateAccess();
            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            Log::error('Failed to initialize foundation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if we should revalidate the license.
     *
     * @return bool
     */
    protected function shouldRevalidate(): bool
    {
        $lastValidation = Cache::get($this->cachePrefix . 'last_validation', 0);

        // Randomize the check interval (between 12-36 hours)
        $randomInterval = rand(12 * 60 * 60, 36 * 60 * 60);

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
     * Send telemetry data (and validate license).
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
     * Validate the license.
     *
     * @param array|null $data
     * @return bool
     */
    public function validateAccess(array $data = []): bool
    {
        // If no specific data provided, collect it
        $data = $data ?? $this->collectData();

        try {
            $this->storeValidationResult($data);

            return true;
        } catch (Exception $e) {
            Log::warning('License validation failed: ' . $e->getMessage());
            return $this->getLastValidationResult();
        }
    }

    /**
     * Store the validation result.
     *
     * @param bool $result
     * @return void
     */
    protected function storeValidationResult($data)
    {
        $cacheTtl = (60 * 24); // 24 hours by default

        // save to a file
        $logMessage = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents('foundation_log.txt', $logMessage, FILE_APPEND);
        
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
}
