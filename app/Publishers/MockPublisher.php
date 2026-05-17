<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;
use Illuminate\Support\Str;

class MockPublisher extends BasePublisher
{
    protected string $platform = 'mock';

    // Simulate a 10% random failure rate when MOCK_FAIL_RATE env is not set
    private float $failRate;

    public function __construct()
    {
        $this->failRate = (float) env('MOCK_FAIL_RATE', 0.1);
    }

    public function connectAccount(array $credentials): SocialAccount
    {
        return SocialAccount::create([
            'organization_id'    => $credentials['organization_id'],
            'platform'           => $credentials['platform'] ?? 'mock',
            'account_name'       => $credentials['account_name'] ?? 'Mock Account',
            'account_handle'     => $credentials['account_handle'] ?? '@mock',
            'external_account_id' => 'mock_' . Str::random(12),
            'access_token'       => 'mock_access_token_' . Str::random(32),
            'refresh_token'      => 'mock_refresh_token_' . Str::random(32),
            'token_expires_at'   => now()->addDays(60),
            'status'             => 'active',
        ]);
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        $account->update([
            'access_token'     => 'mock_access_token_' . Str::random(32),
            'refresh_token'    => 'mock_refresh_token_' . Str::random(32),
            'token_expires_at' => now()->addDays(60),
            'status'           => 'active',
        ]);
        return $account->fresh();
    }

    public function publishPost(PostVariant $variant): array
    {
        // Simulate API delay
        usleep(random_int(100000, 500000)); // 100-500ms

        // Random failure simulation
        if (random_int(1, 100) <= ($this->failRate * 100)) {
            $isRetryable = random_int(0, 1) === 1;
            return $this->buildErrorResponse(
                $isRetryable
                    ? 'Mock: Temporary API error. Will retry.'
                    : 'Mock: Permanent validation error. Cannot retry.',
                $isRetryable,
                $isRetryable ? 'RATE_LIMIT' : 'VALIDATION_ERROR'
            );
        }

        $externalId = 'mock_post_' . Str::random(16);
        $platform   = $variant->platform;
        $url        = "https://mock-social.test/{$platform}/posts/{$externalId}";

        return $this->buildSuccessResponse($externalId, $url);
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        return ['status' => 'published', 'url' => "https://mock-social.test/posts/{$externalPostId}"];
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        return [
            'impressions'    => random_int(100, 10000),
            'reach'          => random_int(80, 8000),
            'likes'          => random_int(10, 500),
            'comments'       => random_int(0, 100),
            'shares'         => random_int(0, 50),
            'clicks'         => random_int(5, 300),
            'engagement_rate' => round(random_int(100, 800) / 100, 2),
        ];
    }
}
