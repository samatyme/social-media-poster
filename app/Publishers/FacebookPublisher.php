<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;

class FacebookPublisher extends BasePublisher
{
    protected string $platform = 'facebook';

    public function connectAccount(array $credentials): SocialAccount
    {
        // TODO: Implement Facebook OAuth flow
        // 1. Exchange auth code for access token via Graph API
        // 2. Fetch page/profile info
        // 3. Store encrypted tokens
        throw new \RuntimeException('Facebook OAuth not yet implemented. Set PUBLISHER_MODE=mock to use mock publisher.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        // TODO: Call Graph API to refresh long-lived token
        throw new \RuntimeException('Facebook token refresh not yet implemented.');
    }

    public function publishPost(PostVariant $variant): array
    {
        // TODO: Implement Facebook Graph API post creation
        // POST /{page-id}/feed with message, link, attached media
        throw new \RuntimeException('Facebook publishing not yet implemented. Set PUBLISHER_MODE=mock.');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET /{post-id}?fields=id,message,permalink_url,created_time
        throw new \RuntimeException('Facebook getPostStatus not yet implemented.');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET /{post-id}/insights
        throw new \RuntimeException('Facebook analytics not yet implemented.');
    }
}
