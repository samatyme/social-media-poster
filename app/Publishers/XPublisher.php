<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;

class XPublisher extends BasePublisher
{
    protected string $platform = 'x';

    public function connectAccount(array $credentials): SocialAccount
    {
        // TODO: X (Twitter) OAuth 2.0 PKCE flow
        throw new \RuntimeException('X (Twitter) OAuth not yet implemented.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        // TODO: POST https://api.twitter.com/2/oauth2/token with refresh_token grant
        throw new \RuntimeException('X token refresh not yet implemented.');
    }

    public function publishPost(PostVariant $variant): array
    {
        // TODO: POST https://api.twitter.com/2/tweets
        // Body: { text, media: { media_ids: [] } }
        throw new \RuntimeException('X publishing not yet implemented.');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET https://api.twitter.com/2/tweets/{id}
        throw new \RuntimeException('X getPostStatus not yet implemented.');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        // TODO: GET https://api.twitter.com/2/tweets/{id}?tweet.fields=public_metrics
        throw new \RuntimeException('X analytics not yet implemented.');
    }
}
