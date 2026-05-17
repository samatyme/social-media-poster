<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;

class LinkedInPublisher extends BasePublisher
{
    protected string $platform = 'linkedin';

    public function connectAccount(array $credentials): SocialAccount
    {
        // TODO: LinkedIn OAuth 2.0
        throw new \RuntimeException('LinkedIn OAuth not yet implemented.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        throw new \RuntimeException('LinkedIn token refresh not yet implemented.');
    }

    public function publishPost(PostVariant $variant): array
    {
        // TODO: POST https://api.linkedin.com/v2/ugcPosts
        throw new \RuntimeException('LinkedIn publishing not yet implemented.');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        throw new \RuntimeException('LinkedIn getPostStatus not yet implemented.');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET https://api.linkedin.com/v2/organizationalEntityShareStatistics
        throw new \RuntimeException('LinkedIn analytics not yet implemented.');
    }
}
