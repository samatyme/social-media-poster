<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Post;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use App\Publishers\PublisherFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function create(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data) {
            $post = Post::create([
                'organization_id' => $user->organization_id,
                'created_by'      => $user->id,
                'title'           => $data['title'] ?? null,
                'base_content'    => $data['base_content'] ?? null,
                'status'          => 'draft',
                'approval_status' => $user->organization->isApprovalRequired()
                    ? 'pending' : 'not_required',
                'timezone'        => $data['timezone'] ?? $user->timezone,
                'tags'            => $data['tags'] ?? null,
            ]);

            // Attach media
            if (!empty($data['media_ids'])) {
                foreach ($data['media_ids'] as $i => $mediaId) {
                    $post->postMedia()->create([
                        'media_asset_id' => $mediaId,
                        'sort_order'     => $i,
                    ]);
                }
            }

            // Create variants for selected accounts
            if (!empty($data['account_ids'])) {
                $this->syncVariants($post, $data['account_ids'], $data['variants'] ?? []);
            }

            return $post->load(['variants.socialAccount', 'postMedia.asset', 'creator']);
        });
    }

    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            $post->update([
                'title'        => $data['title'] ?? $post->title,
                'base_content' => $data['base_content'] ?? $post->base_content,
                'timezone'     => $data['timezone'] ?? $post->timezone,
                'tags'         => $data['tags'] ?? $post->tags,
            ]);

            // Resync media
            if (array_key_exists('media_ids', $data)) {
                $post->postMedia()->delete();
                foreach ($data['media_ids'] ?? [] as $i => $mediaId) {
                    $post->postMedia()->create([
                        'media_asset_id' => $mediaId,
                        'sort_order'     => $i,
                    ]);
                }
            }

            // Resync variants
            if (array_key_exists('account_ids', $data)) {
                $this->syncVariants($post, $data['account_ids'], $data['variants'] ?? []);
            }

            return $post->load(['variants.socialAccount', 'postMedia.asset', 'creator']);
        });
    }

    public function schedule(Post $post, string $scheduledAt, string $timezone): Post
    {
        if (!$post->canBeScheduled()) {
            throw ValidationException::withMessages([
                'status' => 'Post cannot be scheduled in its current state.',
            ]);
        }

        $dt = \Carbon\Carbon::parse($scheduledAt, $timezone)->utc();

        if ($dt->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Scheduled time cannot be in the past.',
            ]);
        }

        if ($post->variants()->count() === 0) {
            throw ValidationException::withMessages([
                'accounts' => 'At least one social account must be selected.',
            ]);
        }

        $this->validateAllVariants($post);

        $post->update([
            'status'       => 'scheduled',
            'scheduled_at' => $dt,
            'timezone'     => $timezone,
        ]);

        $post->variants()->update(['status' => 'queued']);

        return $post;
    }

    public function publishNow(Post $post): void
    {
        if (!$post->canBePublished()) {
            throw ValidationException::withMessages([
                'status' => 'Post cannot be published in its current state.',
            ]);
        }

        $this->validateAllVariants($post);

        $post->update(['status' => 'publishing', 'scheduled_at' => now()]);
        $post->variants()->update(['status' => 'queued']);

        foreach ($post->variants as $variant) {
            \App\Jobs\PublishPostVariantJob::dispatch($variant);
        }
    }

    public function cancel(Post $post): Post
    {
        if (!in_array($post->status, ['scheduled', 'draft', 'approved'])) {
            throw ValidationException::withMessages([
                'status' => 'Only scheduled or draft posts can be cancelled.',
            ]);
        }

        $post->update(['status' => 'cancelled']);
        $post->variants()->whereIn('status', ['queued'])->update(['status' => 'draft']);

        return $post;
    }

    public function duplicate(Post $post, User $user): Post
    {
        return DB::transaction(function () use ($post, $user) {
            $newPost = $post->replicate();
            $newPost->created_by    = $user->id;
            $newPost->status        = 'draft';
            $newPost->approval_status = 'not_required';
            $newPost->scheduled_at  = null;
            $newPost->published_at  = null;
            $newPost->title         = ($post->title ? $post->title . ' (Copy)' : null);
            $newPost->save();

            foreach ($post->postMedia as $pm) {
                $newPost->postMedia()->create([
                    'media_asset_id' => $pm->media_asset_id,
                    'platform'       => $pm->platform,
                    'sort_order'     => $pm->sort_order,
                ]);
            }

            foreach ($post->variants as $variant) {
                $newPost->variants()->create([
                    'social_account_id' => $variant->social_account_id,
                    'platform'          => $variant->platform,
                    'content'           => $variant->content,
                    'hashtags'          => $variant->hashtags,
                    'first_comment'     => $variant->first_comment,
                    'link_url'          => $variant->link_url,
                    'visibility'        => $variant->visibility,
                    'status'            => 'draft',
                    'platform_options'  => $variant->platform_options,
                ]);
            }

            return $newPost->load(['variants.socialAccount', 'postMedia.asset']);
        });
    }

    public function submitForApproval(Post $post): Post
    {
        if ($post->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only drafts can be submitted for approval.']);
        }

        $post->update([
            'status'          => 'pending_approval',
            'approval_status' => 'pending',
        ]);

        return $post;
    }

    public function approve(Post $post, User $approver, ?string $notes = null): Post
    {
        if ($post->approval_status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Post is not pending approval.']);
        }

        $post->update([
            'approval_status' => 'approved',
            'approved_by'     => $approver->id,
            'approved_at'     => now(),
            'approval_notes'  => $notes,
            'status'          => 'approved',
        ]);

        return $post;
    }

    public function reject(Post $post, User $approver, string $notes): Post
    {
        $post->update([
            'approval_status' => 'rejected',
            'approved_by'     => $approver->id,
            'approval_notes'  => $notes,
            'status'          => 'draft',
        ]);

        return $post;
    }

    private function syncVariants(Post $post, array $accountIds, array $variantData): void
    {
        $existingIds = $post->variants()->pluck('social_account_id')->toArray();

        // Remove variants for deselected accounts
        $toRemove = array_diff($existingIds, $accountIds);
        if ($toRemove) {
            $post->variants()->whereIn('social_account_id', $toRemove)->delete();
        }

        $accounts = SocialAccount::whereIn('id', $accountIds)->get()->keyBy('id');

        foreach ($accountIds as $accountId) {
            $account = $accounts[$accountId] ?? null;
            if (!$account) continue;

            $override = collect($variantData)->firstWhere('social_account_id', $accountId) ?? [];

            $post->variants()->updateOrCreate(
                ['social_account_id' => $accountId],
                [
                    'platform'         => $account->platform,
                    'content'          => $override['content'] ?? null,
                    'hashtags'         => $override['hashtags'] ?? null,
                    'first_comment'    => $override['first_comment'] ?? null,
                    'link_url'         => $override['link_url'] ?? null,
                    'visibility'       => $override['visibility'] ?? 'public',
                    'platform_options' => $override['platform_options'] ?? null,
                    'status'           => 'draft',
                ]
            );
        }
    }

    private function validateAllVariants(Post $post): void
    {
        $errors = [];
        foreach ($post->variants()->with('socialAccount', 'post.postMedia.asset')->get() as $variant) {
            $publisher  = PublisherFactory::make($variant->platform);
            $validation = $publisher->validatePost($variant);

            $variant->update([
                'status'            => $validation['valid'] ? 'valid' : 'invalid',
                'validation_errors' => $validation['errors'],
            ]);

            if (!$validation['valid']) {
                $errors[$variant->platform] = $validation['errors'];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['variants' => $errors]);
        }
    }
}
