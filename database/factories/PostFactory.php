<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $org = Organization::factory()->create();
        return [
            'organization_id' => $org->id,
            'created_by'      => User::factory(['organization_id' => $org->id]),
            'title'           => $this->faker->sentence(4),
            'base_content'    => $this->faker->paragraph(),
            'status'          => 'draft',
            'approval_status' => 'not_required',
            'timezone'        => 'UTC',
        ];
    }
}
