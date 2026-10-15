<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Departments and the first administrator (from ADMIN_* in .env). Safe to run again: existing
 * rows are updated, never duplicated.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Department::DEFAULTS as $department) {
            Department::query()->firstOrCreate(['code' => $department['code']], $department);
        }

        $admin = config('organizer.admin');

        if (blank($admin['password'])) {
            $this->command?->warn('ADMIN_PASSWORD is not set in .env — skipped creating the administrator account.');

            return;
        }

        User::query()->updateOrCreate(['username' => strtolower($admin['username'])], [
            'name' => $admin['name'],
            'email' => strtolower($admin['email']),
            'role' => UserRole::Admin,
            'department_id' => Department::query()->where('code', 'IT')->value('id'),
            'job_title' => 'System Administrator',
            'avatar_color' => 'rose',
            'is_active' => true,
            'password' => $admin['password'],
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Administrator \"{$admin['username']}\" is ready.");
    }
}
