<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;

class InstagramPublisher extends BasePublisher
{
    protected string $platform = 'instagram';

    public function connectAccount(array $credentials): SocialAccount
    {
        // TODO: Instagram uses Facebook Graph API with Instagram Basic Display API
        throw new \RuntimeException('Instagram OAuth not yet implemented.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        throw new \RuntimeException('Instagram token refresh not yet implemented.');
    }

    public function publishPost(PostVariant $variant): array
    {
        // TODO: Two-step process:
        // 1. POST /{ig-user-id}/media to create container
        // 2. POST /{ig-user-id}/media_publish to publish
        throw new \RuntimeException('Instagram publishing not yet implemented.');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        throw new \RuntimeException('Instagram getPostStatus not yet implemented.');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET /{media-id}/insights
        throw new \RuntimeException('Instagram analytics not yet implemented.');
    }
}
