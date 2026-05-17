<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    private array $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/mov', 'video/quicktime', 'video/webm',
        'application/pdf',
    ];

    private int $maxFileSizeMb = 500;

    public function upload(UploadedFile $file, User $user): MediaAsset
    {
        $this->validateFile($file);

        $dir      = "organizations/{$user->organization_id}/media/" . now()->format('Y/m');
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs($dir, $fileName, 'public');

        $dimensions = $this->getImageDimensions($file);

        $asset = MediaAsset::create([
            'organization_id' => $user->organization_id,
            'uploaded_by'     => $user->id,
            'file_name'       => $fileName,
            'original_name'   => $file->getClientOriginalName(),
            'file_path'       => $path,
            'mime_type'       => $file->getMimeType(),
            'disk'            => 'public',
            'file_size'       => $file->getSize(),
            'width'           => $dimensions['width'],
            'height'          => $dimensions['height'],
            'status'          => 'ready',
        ]);

        // Dispatch thumbnail generation for videos
        if (str_starts_with($file->getMimeType(), 'video/')) {
            \App\Jobs\ProcessMediaJob::dispatch($asset);
        }

        return $asset;
    }

    public function delete(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete($asset->file_path);

        if ($asset->thumbnail_path) {
            Storage::disk($asset->disk)->delete($asset->thumbnail_path);
        }

        $asset->delete();
    }

    private function validateFile(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), $this->allowedMimes)) {
            throw new \InvalidArgumentException(
                "File type {$file->getMimeType()} is not allowed."
            );
        }

        $sizeMb = $file->getSize() / (1024 * 1024);
        if ($sizeMb > $this->maxFileSizeMb) {
            throw new \InvalidArgumentException(
                "File size {$sizeMb}MB exceeds the maximum of {$this->maxFileSizeMb}MB."
            );
        }
    }

    private function getImageDimensions(UploadedFile $file): array
    {
        if (!str_starts_with($file->getMimeType(), 'image/')) {
            return ['width' => null, 'height' => null];
        }

        try {
            [$width, $height] = getimagesize($file->getPathname());
            return compact('width', 'height');
        } catch (\Throwable) {
            return ['width' => null, 'height' => null];
        }
    }
}
