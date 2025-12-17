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
    private const HAS_BOOTED_KEY = 'crudlog.provider.booted';

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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/logging-service.php' => config_path('logging-service.php'),
            ], 'crudlog-config');
        }

        if ($this->app->bound(self::HAS_BOOTED_KEY)) {
            return;
        }
        $this->app->singleton(self::HAS_BOOTED_KEY, fn () => true);

        if (($this->app->runningInConsole() && !$this->isQueueWorkerProcess()) || $this->isServiceApiRoute()) {
            return;
        }

        $this->app->singleton('crudlog.config', function () {
            $apiKey = config('logging-service.api_key');
            if (!$apiKey) {
                return [];
            }

            $cacheKey = 'crudlog:config:' . hash('sha256', $apiKey);
            $cacheDuration = now()->addHours(1);

            return Cache::remember($cacheKey, $cacheDuration, function () use ($apiKey) {
                try {
                    $configEndpoint = config('logging-service.config_endpoint');
                    if (!$configEndpoint) {
                        throw new \Exception('CrudLog Service: Config Endpoint is not configured.');
                    }

                    $response = Http::withToken($apiKey)->acceptJson()->timeout(5)->get($configEndpoint);

                    if ($response->successful()) {
                        return $response->json();
                    }

                    throw new \Exception('CrudLog Service: Authentication failed or error fetching config. Status: ' . $response->status());

                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                    return []; // Cache an empty array on any failure to prevent hammering the API.
                }
            });
        });

        $config = $this->app->make('crudlog.config');

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

    private function isServiceApiRoute(): bool
    {
        if (!$this->app->bound('request')) {
            return false;
        }
        return request()->is('api/v1/*');
    }

    private function isQueueWorkerProcess(): bool
    {
        if (!$this->app->runningInConsole() || empty($_SERVER['argv'])) {
            return false;
        }
        return in_array('queue:work', $_SERVER['argv']) || in_array('queue:listen', $_SERVER['argv']);
    }
}