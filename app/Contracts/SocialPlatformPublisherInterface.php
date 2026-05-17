<?php

namespace App\Contracts;

use App\Models\PostVariant;
use App\Models\SocialAccount;

interface SocialPlatformPublisherInterface
{
    public function connectAccount(array $credentials): SocialAccount;

    public function refreshToken(SocialAccount $account): SocialAccount;

    public function validatePost(PostVariant $variant): array; // returns ['valid' => bool, 'errors' => []]

    public function publishPost(PostVariant $variant): array;  // returns ['success', 'external_id', 'url', 'error']

    public function getPostStatus(string $externalPostId, SocialAccount $account): array;

    public function getAnalytics(string $externalPostId, SocialAccount $account): array;
}
