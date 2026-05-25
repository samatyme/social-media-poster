<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XPublisher extends BasePublisher
{
    protected string $platform = 'x';

    private const API        = 'https://api.twitter.com/2';
    private const UPLOAD_API = 'https://upload.twitter.com/1.1';

    public function __construct(private array $credentials = []) {}

    public function connectAccount(array $credentials): SocialAccount
    {
        throw new \RuntimeException('Use the OAuth flow to connect X accounts.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        // OAuth 1.0a tokens don't expire
        $account->update(['status' => 'active', 'last_verified_at' => now()]);
        return $account->fresh();
    }

    public function publishPost(PostVariant $variant): array
    {
        $account = $variant->socialAccount;
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
                $mediaId = $this->uploadMedia($account, $asset);
                if ($mediaId) $mediaIds[] = $mediaId;
            }

            $body = ['text' => $content];
            if (!empty($mediaIds)) {
                $body['media'] = ['media_ids' => $mediaIds];
            }

            $url     = self::API . '/tweets';
            $headers = $this->oauth1Header('POST', $url, $account);

            $res = Http::withHeaders($headers)
                ->post($url, $body)
                ->throw()
                ->json();

            $tweetId = $res['data']['id'];
            $handle  = ltrim($account->account_handle ?? '', '@');
            $postUrl = "https://x.com/{$handle}/status/{$tweetId}";

            return $this->buildSuccessResponse($tweetId, $postUrl);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body    = $e->response->json();
            $message = $body['detail'] ?? $body['title'] ?? $e->getMessage();
            $code    = (string) ($body['status'] ?? 'UNKNOWN');

            Log::error('X publish failed', ['error' => $body]);

            $retryable = ($e->response->status() === 429);

            return $this->buildErrorResponse("X: {$message}", $retryable, $code);

        } catch (\Throwable $e) {
            Log::error('X publish exception', ['error' => $e->getMessage()]);
            return $this->buildErrorResponse($e->getMessage(), true);
        }
    }

    private function uploadMedia(SocialAccount $account, $asset): ?string
    {
        try {
            $url      = $this->assetUrl($asset);
            $mimeType = $asset->mime_type ?? 'image/jpeg';
            $isVideo  = str_starts_with($mimeType, 'video/');

            $fileContent = Http::get($url)->throw()->body();
            $category    = $isVideo ? 'tweet_video' : 'tweet_image';
            $uploadUrl   = self::UPLOAD_API . '/media/upload.json';

            // INIT
            $initParams = [
                'command'        => 'INIT',
                'total_bytes'    => strlen($fileContent),
                'media_type'     => $mimeType,
                'media_category' => $category,
            ];
            $initRes = Http::withHeaders($this->oauth1Header('POST', $uploadUrl, $account, $initParams))
                ->asForm()
                ->post($uploadUrl, $initParams)
                ->throw()
                ->json();

            $mediaId = $initRes['media_id_string'];

            // APPEND
            $appendParams = ['command' => 'APPEND', 'media_id' => $mediaId, 'segment_index' => 0];
            Http::withHeaders($this->oauth1Header('POST', $uploadUrl, $account, $appendParams))
                ->attach('media', $fileContent, 'upload')
                ->post($uploadUrl, $appendParams)
                ->throw();

            // FINALIZE
            $finalParams = ['command' => 'FINALIZE', 'media_id' => $mediaId];
            $finalRes = Http::withHeaders($this->oauth1Header('POST', $uploadUrl, $account, $finalParams))
                ->asForm()
                ->post($uploadUrl, $finalParams)
                ->throw()
                ->json();

            if ($isVideo) {
                $this->waitForMediaProcessing($account, $mediaId);
            }

            return $mediaId;

        } catch (\Throwable $e) {
            Log::warning('X media upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function waitForMediaProcessing(SocialAccount $account, string $mediaId, int $maxAttempts = 10): void
    {
        $url = self::UPLOAD_API . '/media/upload.json';

        for ($i = 0; $i < $maxAttempts; $i++) {
            $params = ['command' => 'STATUS', 'media_id' => $mediaId];
            $res    = Http::withHeaders($this->oauth1Header('GET', $url, $account, $params))
                ->get($url, $params)
                ->json();

            $state = $res['processing_info']['state'] ?? 'succeeded';

            if ($state === 'succeeded') return;
            if ($state === 'failed') throw new \RuntimeException('X media processing failed.');

            sleep($res['processing_info']['check_after_secs'] ?? 3);
        }

        throw new \RuntimeException('X media processing timed out.');
    }

    // -------------------------------------------------------------------------
    // OAuth 1.0a signing
    // -------------------------------------------------------------------------

    private function oauth1Header(string $method, string $url, SocialAccount $account, array $extraParams = []): array
    {
        $consumerKey    = $this->credentials['api_key'];
        $consumerSecret = $this->credentials['api_secret'];
        $accessToken    = $account->access_token;
        $tokenSecret    = $account->refresh_token ?? '';

        $oauthParams = [
            'oauth_consumer_key'     => $consumerKey,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_token'            => $accessToken,
            'oauth_version'          => '1.0',
        ];

        // Merge all params for signature base string
        $allParams = array_merge($oauthParams, $extraParams);
        ksort($allParams);

        $paramString = implode('&', array_map(
            fn($k, $v) => rawurlencode($k) . '=' . rawurlencode($v),
            array_keys($allParams),
            array_values($allParams)
        ));

        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);
        $signature  = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;
        ksort($oauthParams);

        $authHeader = 'OAuth ' . implode(', ', array_map(
            fn($k, $v) => rawurlencode($k) . '="' . rawurlencode($v) . '"',
            array_keys($oauthParams),
            array_values($oauthParams)
        ));

        return ['Authorization' => $authHeader];
    }

    private function assetUrl($asset): string
    {
        $path = $asset->url ?? $asset->disk_path ?? '';
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        $url    = self::API . "/tweets/{$externalPostId}";
        $params = ['tweet.fields' => 'created_at,text'];

        $res = Http::withHeaders($this->oauth1Header('GET', $url, $account, $params))
            ->get($url, $params)
            ->json();

        return [
            'status' => isset($res['data']['id']) ? 'published' : 'unknown',
            'url'    => isset($res['data']['id']) ? 'https://x.com/i/status/' . $externalPostId : null,
        ];
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $url    = self::API . "/tweets/{$externalPostId}";
        $params = ['tweet.fields' => 'public_metrics'];

        $res = Http::withHeaders($this->oauth1Header('GET', $url, $account, $params))
            ->get($url, $params)
            ->json();

        $metrics = $res['data']['public_metrics'] ?? [];

        return [
            'impressions'    => $metrics['impression_count'] ?? 0,
            'reach'          => $metrics['impression_count'] ?? 0,
            'likes'          => $metrics['like_count'] ?? 0,
            'engagement_rate'=> 0,
        ];
    }
}
