<?php

namespace Database\Seeders;

use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostVariant;
use App\Models\PublishingLog;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo organization
        $org = Organization::create([
            'name'              => 'Acme Marketing',
            'slug'              => 'acme-marketing',
            'plan'              => 'pro',
            'status'            => 'active',
            'timezone'          => 'America/New_York',
            'max_social_accounts' => 10,
            'max_scheduled_posts' => 100,
            'max_team_members'  => 10,
            'feature_flags'     => ['require_approval' => false],
        ]);

        // Create users
        $owner = User::create([
            'organization_id' => $org->id,
            'name'            => 'Admin User',
            'email'           => 'admin@demo.com',
            'password'        => Hash::make('password'),
            'role'            => 'owner',
            'timezone'        => 'America/New_York',
        ]);

        $editor = User::create([
            'organization_id' => $org->id,
            'name'            => 'Jane Editor',
            'email'           => 'editor@demo.com',
            'password'        => Hash::make('password'),
            'role'            => 'editor',
        ]);

        $approver = User::create([
            'organization_id' => $org->id,
            'name'            => 'Bob Approver',
            'email'           => 'approver@demo.com',
            'password'        => Hash::make('password'),
            'role'            => 'approver',
        ]);

        // Create mock social accounts
        $platforms = [
            ['platform' => 'facebook',  'account_name' => 'Acme Facebook Page',    'account_handle' => '@AcmeMarketing'],
            ['platform' => 'instagram', 'account_name' => 'Acme Instagram',         'account_handle' => '@acme_marketing'],
            ['platform' => 'x',         'account_name' => 'Acme on X',              'account_handle' => '@AcmeMktg'],
            ['platform' => 'linkedin',  'account_name' => 'Acme Marketing LinkedIn', 'account_handle' => 'acme-marketing'],
            ['platform' => 'tiktok',    'account_name' => 'Acme TikTok',            'account_handle' => '@acmetiktok'],
        ];

        $accounts = [];
        foreach ($platforms as $p) {
            $accounts[$p['platform']] = SocialAccount::create(array_merge($p, [
                'organization_id'    => $org->id,
                'external_account_id' => 'ext_' . substr($p['platform'], 0, 3) . '_123',
                'access_token'       => 'mock_token_' . $p['platform'],
                'refresh_token'      => 'mock_refresh_' . $p['platform'],
                'token_expires_at'   => now()->addDays(60),
                'status'             => 'active',
                'last_verified_at'   => now(),
            ]));
        }

        // Create sample media assets
        $mediaAsset = MediaAsset::create([
            'organization_id' => $org->id,
            'uploaded_by'     => $owner->id,
            'file_name'       => 'sample-image.jpg',
            'original_name'   => 'sample-image.jpg',
            'file_path'       => 'organizations/' . $org->id . '/media/sample-image.jpg',
            'mime_type'       => 'image/jpeg',
            'disk'            => 'public',
            'file_size'       => 204800,
            'width'           => 1200,
            'height'          => 630,
            'status'          => 'ready',
        ]);

        // Create sample posts
        $this->createSamplePosts($org, $owner, $editor, $accounts, $mediaAsset);
    }

    private function createSamplePosts($org, $owner, $editor, $accounts, $media): void
    {
        // Published post
        $post1 = Post::create([
            'organization_id' => $org->id,
            'created_by'      => $owner->id,
            'title'           => 'Product Launch Announcement',
            'base_content'    => "We're thrilled to announce the launch of our newest product! 🚀\n\nAfter months of development, we're ready to share it with the world. Check it out at our website!\n\n#ProductLaunch #Innovation #NewProduct",
            'status'          => 'published',
            'approval_status' => 'not_required',
            'scheduled_at'    => now()->subDays(2),
            'published_at'    => now()->subDays(2),
            'timezone'        => 'America/New_York',
        ]);

        foreach (['facebook', 'instagram', 'x'] as $platform) {
            $variant = PostVariant::create([
                'post_id'           => $post1->id,
                'social_account_id' => $accounts[$platform]->id,
                'platform'          => $platform,
                'content'           => $platform === 'x'
                    ? "Big news! We just launched our newest product 🚀 Check it out! #ProductLaunch"
                    : null,
                'status'            => 'published',
            ]);

            PublishingLog::create([
                'post_id'           => $post1->id,
                'post_variant_id'   => $variant->id,
                'social_account_id' => $accounts[$platform]->id,
                'platform'          => $platform,
                'status'            => 'success',
                'external_post_id'  => 'ext_' . $platform . '_' . rand(1000, 9999),
                'external_post_url' => "https://mock-social.test/{$platform}/posts/ext_123",
                'published_at'      => now()->subDays(2),
            ]);
        }

        // Scheduled post
        $post2 = Post::create([
            'organization_id' => $org->id,
            'created_by'      => $editor->id,
            'title'           => 'Weekly Tips Post',
            'base_content'    => "💡 Tip of the week: Focus on creating value for your audience first. Everything else follows.\n\n#MarketingTips #ContentStrategy #GrowthHacking",
            'status'          => 'scheduled',
            'approval_status' => 'not_required',
            'scheduled_at'    => now()->addDays(1)->setHour(10)->setMinute(0),
            'timezone'        => 'America/New_York',
        ]);

        foreach (['facebook', 'linkedin', 'x'] as $platform) {
            PostVariant::create([
                'post_id'           => $post2->id,
                'social_account_id' => $accounts[$platform]->id,
                'platform'          => $platform,
                'status'            => 'queued',
            ]);
        }

        $post2->postMedia()->create(['media_asset_id' => $media->id, 'sort_order' => 0]);

        // Draft post
        Post::create([
            'organization_id' => $org->id,
            'created_by'      => $editor->id,
            'title'           => 'Draft: Summer Campaign',
            'base_content'    => "☀️ Summer is here and we have some exciting things planned! Stay tuned...",
            'status'          => 'draft',
            'approval_status' => 'not_required',
            'timezone'        => 'America/New_York',
        ]);

        // Failed post
        $post4 = Post::create([
            'organization_id' => $org->id,
            'created_by'      => $owner->id,
            'title'           => 'Failed: Instagram Media Post',
            'base_content'    => "Check out our latest work! #design #creative",
            'status'          => 'failed',
            'approval_status' => 'not_required',
            'scheduled_at'    => now()->subHours(3),
            'timezone'        => 'America/New_York',
        ]);

        $failedVariant = PostVariant::create([
            'post_id'           => $post4->id,
            'social_account_id' => $accounts['instagram']->id,
            'platform'          => 'instagram',
            'status'            => 'failed',
        ]);

        PublishingLog::create([
            'post_id'           => $post4->id,
            'post_variant_id'   => $failedVariant->id,
            'social_account_id' => $accounts['instagram']->id,
            'platform'          => 'instagram',
            'status'            => 'failed',
            'error_message'     => 'Media is required for Instagram posts.',
            'error_code'        => 'MEDIA_REQUIRED',
            'is_retryable'      => false,
            'retry_count'       => 1,
        ]);
    }
}
