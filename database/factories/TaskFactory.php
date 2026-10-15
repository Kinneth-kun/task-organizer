<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows for tests. Real tasks are created through TaskService.
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'status' => TaskStatus::Pending,
            'priority' => 'medium',
            'progress' => 0,
            'created_by' => User::factory(),
        ];
    }
}
