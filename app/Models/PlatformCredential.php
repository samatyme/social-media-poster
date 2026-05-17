<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformCredential extends Model
{
    protected $fillable = ['organization_id', 'platform', 'credentials'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function setCredentialsAttribute(array $value): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    public function getCredentialsAttribute(string $value): array
    {
        return json_decode(Crypt::decryptString($value), true);
    }

    // Returns only the fields defined for this platform, masking secrets
    public function toSafeArray(): array
    {
        $creds = $this->credentials;
        $masked = [];
        foreach ($creds as $key => $val) {
            // Show first 4 chars then mask, for secret-like fields
            $isSecret = str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'token');
            $masked[$key] = $isSecret && strlen($val) > 4
                ? substr($val, 0, 4) . str_repeat('*', max(0, strlen($val) - 4))
                : $val;
        }
        return [
            'platform'    => $this->platform,
            'credentials' => $masked,
            'updated_at'  => $this->updated_at,
        ];
    }
}
