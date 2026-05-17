<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'social_account_id', 'platform', 'content',
        'hashtags', 'first_comment', 'link_url', 'visibility',
        'status', 'validation_errors', 'platform_options',
    ];

    protected $casts = [
        'hashtags'          => 'array',
        'validation_errors' => 'array',
        'platform_options'  => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class);
    }

    public function getEffectiveContent(): string
    {
        $content = $this->content ?? $this->post->base_content ?? '';
        if ($this->hashtags) {
            $tags = collect($this->hashtags)->map(fn($t) => str_starts_with($t, '#') ? $t : "#$t")->join(' ');
            $content = trim($content . "\n\n" . $tags);
        }
        return $content;
    }

    public function getPlatformRules(): array
    {
        return config("platform_rules.{$this->platform}", []);
    }

    public function getLatestLog(): ?PublishingLog
    {
        return $this->publishingLogs()->latest()->first();
    }
}
