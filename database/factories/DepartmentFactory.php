<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4)).fake()->unique()->numberBetween(1, 999),
            'color' => 'slate',
            'is_active' => true,
        ];
    }
}
