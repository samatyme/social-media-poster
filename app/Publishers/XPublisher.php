<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XPublisher extends BasePublisher
{
    protected string $platform = 'x';

    private const API = 'https://api.twitter.com/2';
    private const UPLOAD_API = 'https://upload.twitter.com/1.1';

    public function __construct(private array $credentials = []) {}

    public function connectAccount(array $credentials): SocialAccount
    {
        throw new \RuntimeException('Use the OAuth flow to connect X accounts.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        if (!$account->refresh_token) {
            $account->update(['status' => 'expired']);
            throw new \RuntimeException('No refresh token available for X account.');
        }

        try {
            $res = Http::withBasicAuth(
                $this->credentials['api_key'],
                $this->credentials['api_secret']
            )->asForm()->post(self::API . '/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ])->throw()->json();

            $account->update([
                'access_token'     => $res['access_token'],
                'refresh_token'    => $res['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => isset($res['expires_in']) ? now()->addSeconds($res['expires_in']) : null,
                'status'           => 'active',
                'last_verified_at' => now(),
            ]);

            return $account->fresh();

        } catch (\Throwable $e) {
            $account->update(['status' => 'expired']);
            throw $e;
        }
    }

    public function publishPost(PostVariant $variant): array
    {
        $account = $variant->socialAccount;
        $token   = $account->access_token;
        $content = $variant->getEffectiveContent();

        try {
            $mediaAssets = $variant->post->postMedia()
                ->with('asset')
                ->get()
                ->map(fn($pm) => $pm->asset)
                ->filter()
                ->values();

            $mediaIds = [];
            foreach ($mediaAssets->take(4) as $asset) {
                $mediaId = $this->uploadMedia($token, $asset);
                if ($mediaId) $mediaIds[] = $mediaId;
            }

            $body = ['text' => $content];
            if (!empty($mediaIds)) {
                $body['media'] = ['media_ids' => $mediaIds];
            }

            $res = Http::withToken($token)
                ->post(self::API . '/tweets', $body)
                ->throw()
                ->json();

            $tweetId = $res['data']['id'];
            $handle  = ltrim($account->account_handle ?? '', '@');
            $url     = "https://x.com/{$handle}/status/{$tweetId}";

            return $this->buildSuccessResponse($tweetId, $url);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body    = $e->response->json();
            $message = $body['detail'] ?? $body['title'] ?? $e->getMessage();
            $code    = (string) ($body['status'] ?? 'UNKNOWN');

            Log::error('X publish failed', ['error' => $body]);

            // 429 = rate limit, retryable
            $retryable = ($e->response->status() === 429);

            return $this->buildErrorResponse("X: {$message}", $retryable, $code);

        } catch (\Throwable $e) {
            Log::error('X publish exception', ['error' => $e->getMessage()]);
            return $this->buildErrorResponse($e->getMessage(), true);
        }
    }

    private function uploadMedia(string $token, $asset): ?string
    {
        try {
            $url      = $this->assetUrl($asset);
            $mimeType = $asset->mime_type ?? 'image/jpeg';
            $isVideo  = str_starts_with($mimeType, 'video/');

            // Download the file content
            $fileContent = Http::get($url)->throw()->body();

            $category = $isVideo ? 'tweet_video' : 'tweet_image';

            // INIT
            $initRes = Http::withToken($token)
                ->asForm()
                ->post(self::UPLOAD_API . '/media/upload.json', [
                    'command'        => 'INIT',
                    'total_bytes'    => strlen($fileContent),
                    'media_type'     => $mimeType,
                    'media_category' => $category,
                ])->throw()->json();

            $mediaId = $initRes['media_id_string'];

            // APPEND
            Http::withToken($token)
                ->attach('media', $fileContent, 'media')
                ->post(self::UPLOAD_API . '/media/upload.json', [
                    'command'       => 'APPEND',
                    'media_id'      => $mediaId,
                    'segment_index' => 0,
                ])->throw();

            // FINALIZE
            $finalRes = Http::withToken($token)
                ->asForm()
                ->post(self::UPLOAD_API . '/media/upload.json', [
                    'command'  => 'FINALIZE',
                    'media_id' => $mediaId,
                ])->throw()->json();

            // Wait for video processing
            if ($isVideo) {
                $this->waitForMediaProcessing($token, $mediaId);
            }

            return $mediaId;

        } catch (\Throwable $e) {
            Log::warning('X media upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function waitForMediaProcessing(string $token, string $mediaId, int $maxAttempts = 10): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $res    = Http::withToken($token)
                ->get(self::UPLOAD_API . '/media/upload.json', ['command' => 'STATUS', 'media_id' => $mediaId])
                ->json();

            $state  = $res['processing_info']['state'] ?? 'succeeded';

            if ($state === 'succeeded') return;
            if ($state === 'failed') throw new \RuntimeException('X media processing failed.');

            sleep($res['processing_info']['check_after_secs'] ?? 3);
        }

        throw new \RuntimeException('X media processing timed out.');
    }

    private function assetUrl($asset): string
    {
        $path = $asset->url ?? $asset->disk_path ?? '';
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::withToken($account->access_token)
            ->get(self::API . "/tweets/{$externalPostId}", [
                'tweet.fields' => 'created_at,text',
            ])->json();

        return [
            'status' => isset($res['data']['id']) ? 'published' : 'unknown',
            'url'    => isset($res['data']['id'])
                ? 'https://x.com/i/status/' . $externalPostId
                : null,
        ];
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::withToken($account->access_token)
            ->get(self::API . "/tweets/{$externalPostId}", [
                'tweet.fields' => 'public_metrics',
            ])->json();

        $metrics = $res['data']['public_metrics'] ?? [];

        return [
            'impressions'    => $metrics['impression_count'] ?? 0,
            'reach'          => $metrics['impression_count'] ?? 0,
            'likes'          => $metrics['like_count'] ?? 0,
            'engagement_rate'=> 0,
        ];
    }
}
