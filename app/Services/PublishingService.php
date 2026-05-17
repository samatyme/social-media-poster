<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostVariant;
use App\Models\PublishingLog;
use App\Publishers\PublisherFactory;
use Illuminate\Support\Facades\Log;

class PublishingService
{
    public function publishVariant(PostVariant $variant): PublishingLog
    {
        $log = PublishingLog::create([
            'post_id'           => $variant->post_id,
            'post_variant_id'   => $variant->id,
            'social_account_id' => $variant->social_account_id,
            'platform'          => $variant->platform,
            'status'            => 'processing',
            'request_payload'   => [
                'content'    => $variant->getEffectiveContent(),
                'platform'   => $variant->platform,
                'account_id' => $variant->social_account_id,
            ],
        ]);

        $variant->update(['status' => 'publishing']);

        try {
            $account   = $variant->socialAccount;
            $publisher = PublisherFactory::make($variant->platform);
            $result    = $publisher->publishPost($variant);

            if ($result['success']) {
                $log->update([
                    'status'           => 'success',
                    'external_post_id' => $result['external_id'],
                    'external_post_url' => $result['url'],
                    'response_payload' => $result,
                    'published_at'     => now(),
                    'is_retryable'     => false,
                ]);

                $variant->update(['status' => 'published']);

                $this->createOrUpdateAnalyticsRecord($variant, $result);
            } else {
                $log->update([
                    'status'           => 'failed',
                    'error_message'    => $result['error'],
                    'error_code'       => $result['error_code'] ?? null,
                    'is_retryable'     => $result['is_retryable'] ?? true,
                    'response_payload' => $result,
                ]);

                $variant->update(['status' => 'failed']);
            }
        } catch (\Throwable $e) {
            Log::error('Publishing failed', [
                'variant_id' => $variant->id,
                'error'      => $e->getMessage(),
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'is_retryable'  => true,
            ]);

            $variant->update(['status' => 'failed']);
        }

        $this->updatePostStatus($variant->post_id);

        return $log->fresh();
    }

    public function updatePostStatus(int $postId): void
    {
        $post     = Post::find($postId);
        $variants = $post->variants;

        if ($variants->isEmpty()) return;

        $statuses    = $variants->pluck('status');
        $allPublished = $statuses->every(fn($s) => $s === 'published');
        $allFailed    = $statuses->every(fn($s) => $s === 'failed');
        $anyPublished = $statuses->contains('published');
        $anyQueued    = $statuses->contains(fn($s) => in_array($s, ['queued', 'publishing']));

        if ($anyQueued) return; // still in progress

        if ($allPublished) {
            $post->update(['status' => 'published', 'published_at' => now()]);
        } elseif ($allFailed) {
            $post->update(['status' => 'failed']);
        } elseif ($anyPublished) {
            $post->update(['status' => 'partially_published', 'published_at' => now()]);
        }
    }

    private function createOrUpdateAnalyticsRecord(PostVariant $variant, array $result): void
    {
        $variant->post->analytics()->updateOrCreate(
            [
                'post_variant_id'   => $variant->id,
                'social_account_id' => $variant->social_account_id,
            ],
            [
                'platform'         => $variant->platform,
                'external_post_id' => $result['external_id'],
                'external_post_url' => $result['url'],
            ]
        );
    }
}
