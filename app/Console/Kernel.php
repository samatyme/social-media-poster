<?php

namespace App\Console;

use App\Jobs\PublishScheduledPostsJob;
use App\Jobs\RefreshSocialTokenJob;
use App\Models\SocialAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run every minute to catch due scheduled posts
        $schedule->job(new PublishScheduledPostsJob())->everyMinute()
            ->name('publish-scheduled-posts')
            ->withoutOverlapping();

        // Refresh expiring tokens every hour
        $schedule->call(function () {
            SocialAccount::where('status', 'active')
                ->where('token_expires_at', '<=', now()->addHours(24))
                ->each(fn($account) => RefreshSocialTokenJob::dispatch($account));
        })->hourly()->name('refresh-expiring-tokens');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
