<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAnalytic extends Model
{
    protected $fillable = [
        'post_id', 'post_variant_id', 'social_account_id', 'platform',
        'external_post_id', 'external_post_url',
        'impressions', 'reach', 'likes', 'comments', 'shares',
        'saves', 'clicks', 'engagement_rate', 'raw_metrics', 'last_synced_at',
    ];

    protected $casts = [
        'raw_metrics'    => 'array',
        'last_synced_at' => 'datetime',
        'engagement_rate' => 'float',
    ];

    public function post(): BelongsTo        { return $this->belongsTo(Post::class); }
    public function postVariant(): BelongsTo { return $this->belongsTo(PostVariant::class); }
    public function socialAccount(): BelongsTo { return $this->belongsTo(SocialAccount::class); }
}
