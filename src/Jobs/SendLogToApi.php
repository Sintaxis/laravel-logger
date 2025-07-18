<?php

namespace CrudLog\Logger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendLogToApi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Allow for retries if our API is temporarily down
    public int $tries = 3;
    public function backoff(): array
    {
        return [60, 300, 1800]; // Retry after 1 min, 5 mins, 30 mins
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $logData,
        public string $apiKey,
        public string $apiEndpoint
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(10) // Allow a slightly longer timeout for background jobs
                ->post($this->apiEndpoint, $this->logData);
        } catch (\Exception $e) {
            // If the job fails after all retries, this will be logged.
            Log::critical('CrudLog Service: Failed to send log to API after all retries.', [
                'error' => $e->getMessage(),
                'log_data' => $this->logData,
            ]);
            // Re-throw the exception to ensure the job is marked as failed properly.
            throw $e;
        }
    }
}