<?php

namespace App\Publishers;

use App\Models\PostVariant;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher extends BasePublisher
{
    protected string $platform = 'facebook';

    private const GRAPH = 'https://graph.facebook.com/v19.0';

    public function __construct(private array $credentials = []) {}

    public function connectAccount(array $credentials): SocialAccount
    {
        throw new \RuntimeException('Use the OAuth flow to connect Facebook accounts.');
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        // Page tokens don't expire — nothing to refresh
        $account->update(['status' => 'active', 'last_verified_at' => now()]);
        return $account->fresh();
    }

    public function publishPost(PostVariant $variant): array
    {
        $account = $variant->socialAccount;
        $token   = $account->access_token;
        $pageId  = $account->external_account_id;
        $content = $variant->getEffectiveContent();

        try {
            // Collect media attached to this post
            $mediaAssets = $variant->post->postMedia()
                ->with('asset')
                ->get()
                ->map(fn($pm) => $pm->asset)
                ->filter()
                ->values();

            $postId = $mediaAssets->isEmpty()
                ? $this->publishTextPost($pageId, $token, $content)
                : $this->publishMediaPost($pageId, $token, $content, $mediaAssets);

            $url = "https://www.facebook.com/{$postId}";

            return $this->buildSuccessResponse($postId, $url);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body    = $e->response->json();
            $message = $body['error']['message'] ?? $e->getMessage();
            $code    = (string) ($body['error']['code'] ?? 'UNKNOWN');

            Log::error('Facebook publish failed', ['page' => $pageId, 'error' => $body]);

            // Rate limit errors are retryable
            $retryable = in_array($body['error']['code'] ?? 0, [32, 613, 17]);

            return $this->buildErrorResponse("Facebook: {$message}", $retryable, $code);

        } catch (\Throwable $e) {
            Log::error('Facebook publish exception', ['error' => $e->getMessage()]);
            return $this->buildErrorResponse($e->getMessage(), true);
        }
    }

    private function publishTextPost(string $pageId, string $token, string $content): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$pageId}/feed", [
            'message'      => $content,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function publishMediaPost(string $pageId, string $token, string $content, $mediaAssets): string
    {
        $videos = $mediaAssets->filter(fn($a) => str_starts_with($a->mime_type ?? '', 'video/'));
        $images = $mediaAssets->filter(fn($a) => str_starts_with($a->mime_type ?? '', 'image/'));

        // Single video
        if ($videos->isNotEmpty()) {
            return $this->publishVideo($pageId, $token, $content, $videos->first());
        }

        // Single image
        if ($images->count() === 1) {
            return $this->publishSingleImage($pageId, $token, $content, $images->first());
        }

        // Multiple images → attach as photo IDs then post
        if ($images->count() > 1) {
            return $this->publishMultipleImages($pageId, $token, $content, $images);
        }

        return $this->publishTextPost($pageId, $token, $content);
    }

    private function publishSingleImage(string $pageId, string $token, string $content, $asset): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$pageId}/photos", [
            'url'          => $this->assetUrl($asset),
            'caption'      => $content,
            'access_token' => $token,
        ])->throw()->json();

        return $res['post_id'] ?? $res['id'];
    }

    private function publishMultipleImages(string $pageId, string $token, string $content, $images): string
    {
        // Upload each photo as unpublished, then attach to a single post
        $photoIds = $images->map(function ($asset) use ($pageId, $token) {
            $res = Http::asForm()->post(self::GRAPH . "/{$pageId}/photos", [
                'url'          => $this->assetUrl($asset),
                'published'    => 'false',
                'access_token' => $token,
            ])->throw()->json();
            return ['media_fbid' => $res['id']];
        })->values()->all();

        $res = Http::asForm()->post(self::GRAPH . "/{$pageId}/feed", [
            'message'        => $content,
            'attached_media' => json_encode($photoIds),
            'access_token'   => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function publishVideo(string $pageId, string $token, string $content, $asset): string
    {
        $res = Http::asForm()->post(self::GRAPH . "/{$pageId}/videos", [
            'file_url'     => $this->assetUrl($asset),
            'description'  => $content,
            'access_token' => $token,
        ])->throw()->json();

        return $res['id'];
    }

    private function assetUrl($asset): string
    {
        // If stored as a relative path, make it absolute using APP_URL
        $path = $asset->url ?? $asset->disk_path ?? '';
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    public function getPostStatus(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::get(self::GRAPH . "/{$externalPostId}", [
            'fields'       => 'id,message,permalink_url,created_time',
            'access_token' => $account->access_token,
        ])->json();

        return ['status' => isset($res['id']) ? 'published' : 'unknown', 'url' => $res['permalink_url'] ?? null];
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $res = Http::get(self::GRAPH . "/{$externalPostId}/insights", [
            'metric'       => 'post_impressions,post_reach,post_engaged_users,post_reactions_by_type_total',
            'access_token' => $account->access_token,
        ])->json();

        $data = collect($res['data'] ?? [])->keyBy('name');

        return [
            'impressions'    => $data['post_impressions']['values'][0]['value'] ?? 0,
            'reach'          => $data['post_reach']['values'][0]['value'] ?? 0,
            'likes'          => $data['post_reactions_by_type_total']['values'][0]['value']['LIKE'] ?? 0,
            'engagement_rate'=> 0,
        ];
    }
}
