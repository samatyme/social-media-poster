<?php

namespace App\Publishers;

use App\Contracts\SocialPlatformPublisherInterface;
use App\Http\Controllers\Api\PlatformCredentialController;

class PublisherFactory
{
    private static array $map = [
        'facebook'  => FacebookPublisher::class,
        'instagram' => InstagramPublisher::class,
        'tiktok'    => TikTokPublisher::class,
        'x'         => XPublisher::class,
        'linkedin'  => LinkedInPublisher::class,
    ];

    /**
     * Make a publisher for the given platform.
     *
     * @param  string   $platform  e.g. 'facebook'
     * @param  int|null $orgId     Organization ID — required when PUBLISHER_MODE != mock
     */
    public static function make(string $platform, ?int $orgId = null): SocialPlatformPublisherInterface
    {
        if (env('PUBLISHER_MODE', 'mock') === 'mock') {
            return new MockPublisher();
        }

        $class = self::$map[$platform] ?? null;
        if (!$class) {
            throw new \InvalidArgumentException("No publisher registered for platform: {$platform}");
        }

        // Load org-specific API credentials from DB
        $credentials = $orgId ? PlatformCredentialController::getFor($orgId, $platform) : null;
        if (!$credentials) {
            throw new \RuntimeException("No API credentials configured for platform '{$platform}' in this organization. Please add them in Settings → API Credentials.");
        }

        return new $class($credentials);
    }

    public static function mock(): MockPublisher
    {
        return new MockPublisher();
    }
}
