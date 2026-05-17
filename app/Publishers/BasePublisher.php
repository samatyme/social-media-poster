<?php

namespace App\Publishers;

use App\Contracts\SocialPlatformPublisherInterface;
use App\Models\PostVariant;
use App\Models\SocialAccount;

abstract class BasePublisher implements SocialPlatformPublisherInterface
{
    protected string $platform;

    public function validatePost(PostVariant $variant): array
    {
        $errors = [];
        $rules  = config("platform_rules.{$this->platform}", []);
        $content = $variant->getEffectiveContent();

        if (isset($rules['max_text_length']) && mb_strlen($content) > $rules['max_text_length']) {
            $errors[] = "Content exceeds maximum length of {$rules['max_text_length']} characters.";
        }

        if (!empty($rules['requires_media'])) {
            $mediaCount = $variant->post->postMedia()
                ->where(fn($q) => $q->whereNull('platform')->orWhere('platform', $this->platform))
                ->count();
            if ($mediaCount === 0) {
                $errors[] = 'This platform requires at least one media item.';
            }
        }

        if (!empty($rules['requires_video'])) {
            $hasVideo = $variant->post->media()
                ->where(fn($q) => $q->whereNull('platform')->orWhere('platform', $this->platform))
                ->where('mime_type', 'like', 'video/%')
                ->exists();
            if (!$hasVideo) {
                $errors[] = 'This platform requires a video.';
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    protected function buildSuccessResponse(string $externalId, string $url): array
    {
        return [
            'success'      => true,
            'external_id'  => $externalId,
            'url'          => $url,
            'error'        => null,
            'is_retryable' => false,
        ];
    }

    protected function buildErrorResponse(string $message, bool $retryable = true, ?string $code = null): array
    {
        return [
            'success'      => false,
            'external_id'  => null,
            'url'          => null,
            'error'        => $message,
            'error_code'   => $code,
            'is_retryable' => $retryable,
        ];
    }
}
