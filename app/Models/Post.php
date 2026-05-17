<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'created_by', 'approved_by', 'title',
        'base_content', 'status', 'approval_status', 'scheduled_at',
        'timezone', 'published_at', 'approved_at', 'approval_notes',
        'is_recurring', 'recurring_config', 'tags',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'published_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'is_recurring'     => 'boolean',
        'recurring_config' => 'array',
        'tags'             => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'post_media')
            ->withPivot(['platform', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytic::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function isDraft(): bool        { return $this->status === 'draft'; }
    public function isScheduled(): bool    { return $this->status === 'scheduled'; }
    public function isPublished(): bool    { return $this->status === 'published'; }
    public function isFailed(): bool       { return $this->status === 'failed'; }
    public function isPublishing(): bool   { return $this->status === 'publishing'; }
    public function isCancelled(): bool    { return $this->status === 'cancelled'; }

    public function canBeScheduled(): bool
    {
        return in_array($this->status, ['draft', 'approved']) &&
            ($this->approval_status === 'not_required' || $this->approval_status === 'approved');
    }

    public function canBePublished(): bool
    {
        return $this->canBeScheduled()
            || $this->status === 'scheduled'
            || $this->status === 'failed'
            || $this->status === 'publishing'; // retry stuck posts
    }
}
