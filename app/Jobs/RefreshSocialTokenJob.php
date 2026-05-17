<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Publishers\PublisherFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshSocialTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public SocialAccount $account) {}

    public function handle(): void
    {
        if (!$this->account->isTokenExpired()) return;

        try {
            $publisher = PublisherFactory::make($this->account->platform);
            $publisher->refreshToken($this->account);

            Log::info("Refreshed token for social account #{$this->account->id}");
        } catch (\Throwable $e) {
            $this->account->update(['status' => 'expired']);
            Log::error("Failed to refresh token for account #{$this->account->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
