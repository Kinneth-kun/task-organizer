<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['organizer.inline_deadline_checks' => false]);
        Settings::flush();
    }

    protected function department(string $code = 'IT'): Department
    {
        return Department::query()->firstOrCreate(['code' => $code], ['name' => $code.' Department', 'color' => 'sky']);
    }

    protected function person(UserRole $role = UserRole::Member, ?Department $department = null, array $attributes = []): User
    {
        return User::factory()->role($role)->create([
            'department_id' => ($department ?? $this->department())->id,
            ...$attributes,
        ]);
    }
}
