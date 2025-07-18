<?php

namespace CrudLog\Logger\Listeners;

use CrudLog\Logger\Events\ModelLoggableEvent;
use CrudLog\Logger\Jobs\SendLogToApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogModelChange
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ModelLoggableEvent $event): void
    {
        // In a real package, these would be read from a published config file.
        // The config file would read from the tenant's .env file.
        $dispatchMethod = config('logging-service.dispatch_method', env('CRUDLOG_DISPATCH_METHOD', 'async'));
        $apiKey = config('logging-service.api_key', env('CRUDLOG_API_KEY'));
        $apiEndpoint = config('logging-service.endpoint', env('CRUDLOG_ENDPOINT', 'http://crudlog.test/api/v1/log/async'));
Log::info('CrudLog Service: Logging event', [
            'action' => $event->action,
            'model' => get_class($event->loggable),
            'model_id' => $event->loggable->getKey(),
            'payload' => $event->payload,
        ]);
        if (!$apiKey || !$apiEndpoint) {
            Log::error('CrudLog Service: API Key or Endpoint is not configured. Logging is disabled.');
            return;
        }

        // Assemble the log data payload
        $actingUser = Auth::user();
        $details = [];
        if ($event->action === 'updated') {
            $details['old_values'] = $event->payload['old'] ?? [];
            $details['new_values'] = $event->loggable->getChanges();
        } else {
            // For created/deleted, log the full model attributes at that point in time.
            $details['attributes'] = $event->loggable->toArray();
        }

        $logData = [
            'action_type' => $event->action,
            'entity_type' => class_basename($event->loggable),
            'entity_id' => (string) $event->loggable->getKey(),
            'user_identifier' => $actingUser ? (string) $actingUser->getAuthIdentifier() : null,
            'user_name' => $actingUser ? ($actingUser->name ?? 'Unnamed User') : 'System/Unknown',
            'details' => $details,
            'logged_at' => now()->toIso8601String(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        if ($dispatchMethod === 'async') {
            SendLogToApi::dispatch($logData, $apiKey, $apiEndpoint);
        } else {
        // Make a fast, non-blocking "fire-and-forget" request
            try {
                Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(2) // Short timeout (e.g., 2 seconds)
                    ->post($apiEndpoint, $logData);
            } catch (\Exception $e) {
                // If our service is down or the request times out, we log the error
                // on the client's server but do NOT crash their application process.
                Log::error('CrudLog Service: Failed to send log to API. ' . $e->getMessage());
            }
        }
    }
}