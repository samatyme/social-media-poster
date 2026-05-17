<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        $platform = $this->faker->randomElement(['facebook', 'instagram', 'x', 'linkedin', 'tiktok']);
        return [
            'organization_id'    => Organization::factory(),
            'platform'           => $platform,
            'account_name'       => $this->faker->company() . ' ' . ucfirst($platform),
            'account_handle'     => '@' . $this->faker->userName(),
            'external_account_id' => Str::random(20),
            'access_token'       => 'mock_access_' . Str::random(32),
            'refresh_token'      => 'mock_refresh_' . Str::random(32),
            'token_expires_at'   => now()->addDays(60),
            'status'             => 'active',
            'last_verified_at'   => now(),
        ];
    }
}
