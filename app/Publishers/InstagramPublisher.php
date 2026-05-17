<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramPublisher extends BasePublisher
{
    protected string $platform = 'instagram';

    private const GRAPH = 'https://graph.facebook.com/v19.0';

    public function __construct(private array $credentials = []) {}

    public function connectAccount(array $credentials): SocialAccount
    {
        throw new \RuntimeException('Use the Manual Token flow to connect Instagram accounts.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        $account->update(['status' => 'active', 'last_verified_at' => now()]);
        return $account->fresh();
    }

    public function publishPost(PostVariant $variant): array
    {
        $account  = $variant->socialAccount;
        $igUserId = $account->external_account_id;
        $token    = $account->access_token;
        $caption  = $variant->getEffectiveContent();

        try {
            $mediaAssets = $variant->post->postMedia()
                ->with('asset')
                ->get()
                ->map(fn($pm) => $pm->asset)
                ->filter()
                ->values();

            $images = $mediaAssets->filter(fn($a) => str_starts_with($a->mime_type ?? '', 'image/'));
            $videos = $mediaAssets->filter(fn($a) => str_starts_with($a->mime_type ?? '', 'video/'));

            if ($videos->isNotEmpty()) {
                $mediaId = $this->createVideoContainer($igUserId, $token, $caption, $videos->first());
            } elseif ($images->count() > 1) {
                $mediaId = $this->createCarouselContainer($igUserId, $token, $caption, $images);
            } elseif ($images->count() === 1) {
                $mediaId = $this->createImageContainer($igUserId, $token, $caption, $images->first());
            } else {
                return $this->buildErrorResponse('Instagram requires at least one image or video.', false);
            }

            $this->waitForContainer($igUserId, $token, $mediaId);

            $postId = $this->publishContainer($igUserId, $token, $mediaId);
            $url    = "https://www.instagram.com/p/{$postId}/";

            return $this->buildSuccessResponse($postId, $url);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body    = $e->response->json();
            $message = $body['error']['message'] ?? $e->getMessage();
            $code    = (string) ($body['error']['code'] ?? 'UNKNOWN');

            Log::error('Instagram publish failed', ['ig_user' => $igUserId, 'error' => $body]);

            $retryable = in_array($body['error']['code'] ?? 0, [32, 613, 17]);

            return $this->buildErrorResponse("Instagram: {$message}", $retryable, $code);

        } catch (\Throwable $e) {
            Log::error('Instagram publish exception', ['error' => $e->getMessage()]);
            return $this->buildErrorResponse($e->getMessage(), true);
        }
    }

    private function createImageContainer(string $igUserId, string $token, string $caption, $asset): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$igUserId}/media", [
            'image_url'    => $this->assetUrl($asset),
            'caption'      => $caption,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function createVideoContainer(string $igUserId, string $token, string $caption, $asset): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$igUserId}/media", [
            'media_type'   => 'REELS',
            'video_url'    => $this->assetUrl($asset),
            'caption'      => $caption,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function createCarouselContainer(string $igUserId, string $token, string $caption, $images): string
    {
        // Create individual item containers first
        $childIds = $images->map(function ($asset) use ($igUserId, $token) {
            $res = Http::asForm()->post(self::GRAPH . "/{$igUserId}/media", [
                'image_url'    => $this->assetUrl($asset),
                'is_carousel_item' => 'true',
                'access_token' => $token,
            ])->throw()->json();
            return $res['id'];
        })->values()->all();

        // Create carousel container
        $res = Http::asForm()->post(self::GRAPH . "/{$igUserId}/media", [
            'media_type'   => 'CAROUSEL',
            'children'     => implode(',', $childIds),
            'caption'      => $caption,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function waitForContainer(string $igUserId, string $token, string $mediaId, int $maxAttempts = 10): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $res = Http::get(self::GRAPH . "/{$mediaId}", [
                'fields'       => 'status_code',
                'access_token' => $token,
            ])->json();

            $status = $res['status_code'] ?? 'ERROR';

            if ($status === 'FINISHED') return;
            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new \RuntimeException("Instagram media container failed with status: {$status}");
            }

            sleep(3);
        }

        throw new \RuntimeException('Instagram media container timed out waiting to be ready.');
    }

    private function publishContainer(string $igUserId, string $token, string $mediaId): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$igUserId}/media_publish", [
            'creation_id'  => $mediaId,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function assetUrl($asset): string
    {
        $path = $asset->url ?? $asset->disk_path ?? '';
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::get(self::GRAPH . "/{$externalPostId}", [
            'fields'       => 'id,caption,permalink,timestamp',
            'access_token' => $account->access_token,
        ])->json();

        return [
            'status' => isset($res['id']) ? 'published' : 'unknown',
            'url'    => $res['permalink'] ?? null,
        ];
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::get(self::GRAPH . "/{$externalPostId}/insights", [
            'metric'       => 'impressions,reach,likes_count,comments_count',
            'access_token' => $account->access_token,
        ])->json();

        $data = collect($res['data'] ?? [])->keyBy('name');

        return [
            'impressions'    => $data['impressions']['values'][0]['value'] ?? 0,
            'reach'          => $data['reach']['values'][0]['value'] ?? 0,
            'likes'          => $data['likes_count']['values'][0]['value'] ?? 0,
            'engagement_rate'=> 0,
        ];
    }
}
