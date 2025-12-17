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
    public function handle(ModelLoggableEvent $event): void
    {
        $dispatchMethod = config('logging-service.dispatch_method', env('CRUDLOG_DISPATCH_METHOD', 'async'));
        $apiKey = config('logging-service.api_key', env('CRUDLOG_API_KEY'));
        $apiEndpoint = config('logging-service.endpoint', env('CRUDLOG_ENDPOINT', 'http://crudlog.com/api/v1/log/async'));
        if (!$apiKey || !$apiEndpoint) {
            Log::error('CrudLog Service: API Key or Endpoint is not configured. Logging is disabled.');
            return;
        }

        $actingUser = Auth::user();
        $details = [];
        if ($event->action === 'updated') {
            $details['old_values'] = $event->payload['old'] ?? [];
            $details['new_values'] = $event->payload['filtered_new_values'] ?? [];
        } else {
            $details['attributes'] = $event->payload['filtered_attributes'] ?? [];
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
            try {
                Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(2)
                    ->post($apiEndpoint, $logData);
            } catch (\Exception $e) {
                Log::error('CrudLog Service: Failed to send log to API. ' . $e->getMessage());
            }
        }
    }
}