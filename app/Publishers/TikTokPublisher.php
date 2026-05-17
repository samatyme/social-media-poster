<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;

class TikTokPublisher extends BasePublisher
{
    protected string $platform = 'tiktok';

    public function connectAccount(array $credentials): SocialAccount
    {
        // TODO: TikTok OAuth 2.0
        throw new \RuntimeException('TikTok OAuth not yet implemented.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        throw new \RuntimeException('TikTok token refresh not yet implemented.');
    }

    public function publishPost(PostVariant $variant): array
    {
        // TODO: TikTok Content Posting API
        // POST https://open.tiktokapis.com/v2/post/publish/video/init/
        throw new \RuntimeException('TikTok publishing not yet implemented.');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        throw new \RuntimeException('TikTok getPostStatus not yet implemented.');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        throw new \RuntimeException('TikTok analytics not yet implemented.');
    }
}
