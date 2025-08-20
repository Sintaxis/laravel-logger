<?php

namespace CrudLog\Logger\Providers;

use CrudLog\Logger\Events\ModelLoggableEvent;
use CrudLog\Logger\Listeners\LogModelChange;
use CrudLog\Logger\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class LoggableServiceProvider extends ServiceProvider
{
    /**
     * A unique key to register in the service container as a "has run" flag.
     * This is the most robust way to prevent the boot logic from running multiple times.
     */
    private const HAS_BOOTED_KEY = 'crudlog.provider.booted';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge the package's config file with the application's config.
        // This allows the user to only define the keys they want to override.
        $this->mergeConfigFrom(
            __DIR__.'/../../config/logging-service.php', 'logging-service'
        );

        // Explicitly register our event listener.
        Event::listen(
            ModelLoggableEvent::class,
            LogModelChange::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make the config file publishable via `php artisan vendor:publish`
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/logging-service.php' => config_path('logging-service.php'),
            ], 'crudlog-config');
        }

        // --- DEFINITIVE "RUN ONCE" AND CONTEXT GUARDS ---

        // 1. Use the container singleton as the primary guard to ensure this logic runs only once per lifecycle.
        if ($this->app->bound(self::HAS_BOOTED_KEY)) {
            return;
        }
        $this->app->singleton(self::HAS_BOOTED_KEY, fn () => true);

        // 2. Guard against our own API routes to prevent recursion.
        if ($this->isServiceApiRoute()) {
            return;
        }

        // 3. Guard against irrelevant console commands, but ALLOW queue workers.
        if ($this->app->runningInConsole() && !$this->isQueueWorkerProcess()) {
            return;
        }

        // --- END OF GUARDS ---

        $apiKey = config('logging-service.api_key');
        if (!$apiKey) {
            // Silently exit if the service is not configured.
            return;
        }

        $cacheKey = 'crudlog:config:' . hash('sha256', $apiKey);
        $config = Cache::get($cacheKey);

        if (is_null($config)) {
            $configEndpoint = config('logging-service.config_endpoint');
            $fetchedConfig = []; // Default to empty array on any failure
            $cacheDuration = now()->addHours(1);

            try {
                if (!$configEndpoint) {
                    throw new \Exception('CrudLog Service: Config Endpoint is not configured.');
                }

                $response = Http::withToken($apiKey)->acceptJson()->timeout(5)->get($configEndpoint);

                if ($response->successful()) {
                    $fetchedConfig = $response->json();
                } else {
                    throw new \Exception('CrudLog Service: Authentication failed or error fetching config. Status: ' . $response->status());
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            } finally {
                // Always cache the result (even an empty array on failure) to prevent hammering the API.
                Cache::put($cacheKey, $fetchedConfig, $cacheDuration);
                $config = $fetchedConfig;
            }
        }

        if (!empty($config) && ($config['implicit']['enabled'] ?? false) === true) {
            $trackedModels = $config['implicit']['tracked_models'] ?? [];
            foreach ($trackedModels as $modelConfig) {
                $modelClass = $modelConfig['name'] ?? null;
                if ($modelClass && class_exists($modelClass)) {
                    $modelClass::observe(ActivityLogObserver::class);
                } else {
                    Log::warning("CrudLog Service: Configured model class [{$modelClass}] not found or name is missing.");
                }
            }
        }
    }

    /**
     * Check if the current request is targeting one of our own internal API routes.
     */
    private function isServiceApiRoute(): bool
    {
        if (!$this->app->bound('request')) {
            return false;
        }
        return request()->is('api/v1/*');
    }

    /**
     * Check if the application is running a queue worker command.
     */
    private function isQueueWorkerProcess(): bool
    {
        if (!$this->app->runningInConsole() || empty($_SERVER['argv'])) {
            return false;
        }
        return in_array('queue:work', $_SERVER['argv']) || in_array('queue:listen', $_SERVER['argv']);
    }
}