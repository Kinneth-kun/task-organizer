<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Services\UserService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'These credentials do not match our records.';

    public function test_sign_in_with_username_or_email(): void
    {
        $user = $this->person(attributes: ['username' => 'jane.doe', 'email' => 'jane@example.com']);

        $this->post(route('login.store'), ['login' => 'JANE.DOE', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => 'jane@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failures_get_one_generic_message(): void
    {
        $this->person(attributes: ['username' => 'jane.doe']);

        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'wrong'])->assertSessionHasErrors(['login' => self::GENERIC]);
        $this->post(route('login.store'), ['login' => 'nobody', 'password' => 'wrong'])->assertSessionHasErrors(['login' => self::GENERIC]);
        $this->assertGuest();
    }

    public function test_accounts_lock_after_repeated_failures(): void
    {
        Settings::set(['security.max_login_attempts' => 3, 'security.lockout_minutes' => 15]);
        $user = $this->person(attributes: ['username' => 'jane.doe']);

        foreach (range(1, 3) as $i) {
            $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'wrong'])->assertSessionHasErrors(['login' => self::GENERIC]);
        }
        $this->assertTrue($user->refresh()->isLocked());

        // Wrong password while locked: still generic, so the lock isn't revealed.
        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'wrong'])->assertSessionHasErrors(['login' => self::GENERIC]);

        // Right password while locked: told about the lock, not signed in.
        $response = $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'password']);
        $this->assertStringContainsString('locked', session('errors')->first('login'));
        $this->assertGuest();

        app(UserService::class)->unlock($user, $this->person(UserRole::Admin));
        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        $this->person(attributes: ['username' => 'jane.doe', 'is_active' => false]);

        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'wrong'])->assertSessionHasErrors(['login' => self::GENERIC]);
        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'password']);
        $this->assertStringContainsString('deactivated', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_deactivation_and_password_reset_revoke_sessions(): void
    {
        $admin = $this->person(UserRole::Admin);
        $user = $this->person();
        DB::table('sessions')->insert([
            ['id' => 'a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $token = $user->remember_token;

        app(UserService::class)->resetPassword($user, 'New-password-1', $admin);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNotSame($token, $user->refresh()->remember_token);

        DB::table('sessions')->insert(['id' => 'c', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        app(UserService::class)->deactivate($user, $admin);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());

        // A deactivated user with a live session is signed out on the next request.
        $this->actingAs($user->refresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sign_in_is_throttled_per_ip(): void
    {
        foreach (range(1, 20) as $i) {
            $this->post(route('login.store'), ['login' => 'nobody', 'password' => 'x']);
        }

        $this->post(route('login.store'), ['login' => 'nobody', 'password' => 'x'])->assertStatus(429);
    }

    public function test_sign_ins_are_audited(): void
    {
        $user = $this->person(attributes: ['username' => 'jane.doe']);
        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'nope']);
        $this->post(route('login.store'), ['login' => 'jane.doe', 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', ['event' => 'auth.failed']);
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.login', 'causer_id' => $user->id]);
    }
}
