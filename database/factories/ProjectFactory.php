<?php

namespace Database\Factories;

use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows for tests. Real projects are created through ProjectService.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'created_by' => fn (array $attributes) => $attributes['owner_id'],
            'status' => ProjectStatus::Active,
            'priority' => 'medium',
            'color' => 'indigo',
            'progress' => 0,
            'health' => ProjectHealth::OnTrack,
        ];
    }
}
