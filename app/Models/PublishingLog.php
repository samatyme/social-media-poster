<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'post_variant_id', 'social_account_id', 'platform',
        'status', 'external_post_id', 'external_post_url',
        'request_payload', 'response_payload', 'error_message', 'error_code',
        'is_retryable', 'retry_count', 'next_retry_at', 'published_at',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'is_retryable'     => 'boolean',
        'next_retry_at'    => 'datetime',
        'published_at'     => 'datetime',
    ];

    public function post(): BelongsTo         { return $this->belongsTo(Post::class); }
    public function postVariant(): BelongsTo  { return $this->belongsTo(PostVariant::class); }
    public function socialAccount(): BelongsTo { return $this->belongsTo(SocialAccount::class); }

    public function isSuccess(): bool  { return $this->status === 'success'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function canRetry(): bool   { return $this->is_retryable && $this->retry_count < 3; }
}
