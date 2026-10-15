<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use App\Support\Palette;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'username' => Str::lower(Str::slug(Str::before($name, ' '), '')).'.'.fake()->unique()->numberBetween(100, 99999),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Member,
            'department_id' => null,
            'job_title' => fake()->jobTitle(),
            'avatar_color' => fake()->randomElement(Palette::CHOICES),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }

    public function executive(): static
    {
        return $this->role(UserRole::Executive);
    }

    public function manager(): static
    {
        return $this->role(UserRole::Manager);
    }

    public function intern(): static
    {
        return $this->role(UserRole::Intern);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(['department_id' => $department->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
