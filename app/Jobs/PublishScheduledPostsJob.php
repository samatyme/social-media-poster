<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $posts = Post::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with(['variants.socialAccount'])
            ->get();

        foreach ($posts as $post) {
            try {
                $post->update(['status' => 'publishing']);

                foreach ($post->variants()->where('status', 'queued')->get() as $variant) {
                    PublishPostVariantJob::dispatch($variant);
                }

                Log::info("Dispatched publishing jobs for post #{$post->id}");
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch post #{$post->id}: " . $e->getMessage());
            }
        }
    }
}
