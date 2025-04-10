<?php

namespace Jadesdev\Foundation;

use Illuminate\Support\ServiceProvider;
use Jadesdev\Foundation\Services\TelemetryService;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/foundation.php',
            'foundation'
        );

        // Register the telemetry service
        $this->app->singleton('foundation.telemetry', function ($app) {
            return new TelemetryService();
        });

        // Register the main service
        $this->app->singleton('foundation', function ($app) {
            return new FoundationManager(
                $app['foundation.telemetry']
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/foundation.php' => config_path('foundation.php'),
        ], 'foundation-config');

        // Initialize the telemetry service (license check)
        if (!$this->app->runningInConsole()) {
            $telemetry = $this->app->make('foundation.telemetry');
            $telemetry->initialize();

            // Check access validity and handle if invalid
            if (!$telemetry->isAccessValid() && !$telemetry->isInGracePeriod()) {
                $telemetry->handleInvalidAccess();
            }
        }
    }
}
