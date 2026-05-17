<?php

namespace App\Publishers;

use App\Contracts\SocialPlatformPublisherInterface;

class PublisherFactory
{
    private static array $map = [
        'facebook'  => FacebookPublisher::class,
        'instagram' => InstagramPublisher::class,
        'tiktok'    => TikTokPublisher::class,
        'x'         => XPublisher::class,
        'linkedin'  => LinkedInPublisher::class,
        'mock'      => MockPublisher::class,
    ];

    public static function make(string $platform): SocialPlatformPublisherInterface
    {
        if (env('PUBLISHER_MODE', 'mock') === 'mock') {
            return new MockPublisher();
        }

        $class = self::$map[$platform] ?? null;

        if (!$class) {
            throw new \InvalidArgumentException("No publisher registered for platform: {$platform}");
        }

        return new $class();
    }

    public static function mock(): MockPublisher
    {
        return new MockPublisher();
    }
}
