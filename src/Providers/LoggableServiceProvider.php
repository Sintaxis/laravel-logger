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
     */
    private const HAS_BOOTED_KEY = 'crudlog.provider.booted';
    protected static bool $observersAttached = false;

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/logging-service.php', 'logging-service'
        );
        Event::listen(
            ModelLoggableEvent::class,
            LogModelChange::class
        );
    }

    public function boot(): void
    {
        // This makes the config file publishable via `vendor:publish`
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/logging-service.php' => config_path('logging-service.php'),
            ], 'crudlog-config');
        }
        
        if (self::$observersAttached) {
            return;
        }

        // --- CONTAINER SINGLETON GUARD ---
        // Check if we've already marked this provider as booted in the service container.
        if ($this->app->bound(self::HAS_BOOTED_KEY)) {
            return;
        }

        // Mark this provider as booted for the rest of this request's lifecycle.
        // We bind a simple boolean `true` as a singleton.
        $this->app->singleton(self::HAS_BOOTED_KEY, fn () => true);

        // Don't run for most console commands or our own API routes.
        if (($this->app->runningInConsole() && !$this->isQueueWorker()) || $this->isServiceApiRoute()) {
            return;
        }

        $apiKey = config('logging-service.api_key', env('CRUDLOG_API_KEY'));
        if (!$apiKey) {
            return;
        }

        $cacheKey = 'crudlog:config:' . hash('sha256', $apiKey);
        $config = Cache::get($cacheKey);

        if (is_null($config) || !is_array($config) || empty($config)) {
            $configEndpoint = config('logging-service.config_endpoint', env('CRUDLOG_CONFIG_ENDPOINT'));
            $fetchedConfig = [];
            $cacheDuration = now()->addHours(1);

            try {
                if (!$configEndpoint) throw new \Exception('CrudLog Service: Config Endpoint is not configured.');
                $response = Http::withToken($apiKey)->acceptJson()->timeout(5)->get($configEndpoint);

                if ($response->successful()) {
                    $fetchedConfig = $response->json();
                    Log::info('CrudLog Service: Fetched and cached fresh configuration.');
                } else {
                    throw new \Exception('Failed to fetch configuration from API. Status: ' . $response->status());
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            } finally {
                Cache::put($cacheKey, $fetchedConfig ?? [], $cacheDuration);
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
                    Log::warning("CrudLog Service: Configured model class [{$modelClass}] not found or name is missing in config.");
                }
            }
        }
        self::$observersAttached = true;
    }

    private function isServiceApiRoute(): bool
    {
        return request()->is('api/v1/*');
    }

    private function isQueueWorker(): bool
    {
        return $this->app->runningInConsole() && isset($_SERVER['argv']) && in_array('queue:work', $_SERVER['argv']);
    }
}