<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    protected $fillable = ['post_id', 'media_asset_id', 'platform', 'sort_order'];

    public function post(): BelongsTo    { return $this->belongsTo(Post::class); }
    public function asset(): BelongsTo  { return $this->belongsTo(MediaAsset::class, 'media_asset_id'); }
}
