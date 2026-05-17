<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'plan', 'status', 'logo_path', 'website',
        'timezone', 'settings', 'feature_flags',
        'max_social_accounts', 'max_scheduled_posts',
        'max_team_members', 'max_storage_bytes',
    ];

    protected $casts = [
        'settings'         => 'array',
        'feature_flags'    => 'array',
        'max_storage_bytes' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->feature_flags[$feature] ?? false);
    }

    public function isApprovalRequired(): bool
    {
        return $this->hasFeature('require_approval');
    }
}
