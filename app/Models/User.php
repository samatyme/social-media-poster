<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'email', 'password',
        'role', 'avatar_path', 'timezone', 'notification_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'          => 'datetime',
        'password'                   => 'hashed',
        'notification_preferences'   => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'created_by');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'uploaded_by');
    }

    public function isOwner(): bool   { return $this->role === 'owner'; }
    public function isAdmin(): bool   { return in_array($this->role, ['owner', 'admin']); }
    public function isEditor(): bool  { return in_array($this->role, ['owner', 'admin', 'editor']); }
    public function isApprover(): bool { return in_array($this->role, ['owner', 'admin', 'approver']); }
    public function isViewer(): bool  { return $this->role === 'viewer'; }

    public function canPublish(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'editor']);
    }
}
