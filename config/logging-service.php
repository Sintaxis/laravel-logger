<?php

// config/logging-service.php

return [
    /*
    |--------------------------------------------------------------------------
    | CrudLog API Key
    |--------------------------------------------------------------------------
    |
    | This is the API key for your account, generated from your CrudLog dashboard.
    | It is required for your application to communicate with the CrudLog service.
    |
    */
    'api_key' => env('CRUDLOG_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | CrudLog API Endpoints
    |--------------------------------------------------------------------------
    |
    | These are the API endpoints for the CrudLog service. You should not
    | need to change these unless you are using a self-hosted instance
    | or have been instructed to by CrudLog support.
    |
    */
    'config_endpoint' => env('CRUDLOG_CONFIG_ENDPOINT', 'https://crudlog.test/api/v1/config'), // Replace with your production URL
    'endpoint' => env('CRUDLOG_ENDPOINT', 'https://crudlog.test/api/v1/log/async'), // Replace with your production URL

    /*
    |--------------------------------------------------------------------------
    | Dispatch Method
    |--------------------------------------------------------------------------
    |
    | Choose how log data is sent from your server to our service.
    |
    | 'async' - (Recommended) Pushes logs to your application's queue for the
    |           best performance. Requires a configured queue worker.
    | 'sync'  - Sends a quick, non-blocking HTTP request during the web request.
    |           Simpler, no queue needed, but may introduce minor latency.
    |
    */
    'dispatch_method' => env('CRUDLOG_DISPATCH_METHOD', 'async'),
];