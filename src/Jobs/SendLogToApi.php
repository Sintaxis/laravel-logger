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

    public int $tries = 3;
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function __construct(
        public array $logData,
        public string $apiKey,
        public string $apiEndpoint
    ) {}

    public function handle(): void
    {
        try {
            Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiEndpoint, $this->logData);
        } catch (\Exception $e) {
            Log::critical('CrudLog Service: Failed to send log to API after all retries.', [
                'error' => $e->getMessage(),
                'log_data' => $this->logData,
            ]);
            throw $e;
        }
    }
}