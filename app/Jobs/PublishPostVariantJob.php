<?php

namespace App\Jobs;

use App\Models\PostVariant;
use App\Services\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishPostVariantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public PostVariant $variant) {}

    public function handle(PublishingService $publishingService): void
    {
        $publishingService->publishVariant($this->variant);
    }

    public function failed(\Throwable $exception): void
    {
        $this->variant->update(['status' => 'failed']);

        \App\Models\PublishingLog::create([
            'post_id'           => $this->variant->post_id,
            'post_variant_id'   => $this->variant->id,
            'social_account_id' => $this->variant->social_account_id,
            'platform'          => $this->variant->platform,
            'status'            => 'failed',
            'error_message'     => 'Job permanently failed: ' . $exception->getMessage(),
            'is_retryable'      => false,
            'retry_count'       => $this->attempts(),
        ]);

        (new PublishingService())->updatePostStatus($this->variant->post_id);
    }

    public function backoff(): array
    {
        return [30, 60, 120]; // seconds between retries
    }
}
