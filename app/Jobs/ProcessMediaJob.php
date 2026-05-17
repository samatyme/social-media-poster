<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public MediaAsset $asset) {}

    public function handle(): void
    {
        $this->asset->update(['status' => 'processing']);

        // TODO: Generate video thumbnail using FFmpeg or cloud service
        // For now we just mark it ready
        // Example with FFmpeg:
        // $thumbnailPath = str_replace('.mp4', '_thumb.jpg', $this->asset->file_path);
        // exec("ffmpeg -i {$inputPath} -ss 00:00:01 -vframes 1 {$outputPath}");

        $this->asset->update(['status' => 'ready']);
    }

    public function failed(\Throwable $exception): void
    {
        $this->asset->update(['status' => 'failed']);
    }
}
