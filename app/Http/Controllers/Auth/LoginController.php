<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in by username or email.
 *
 * Protection: a per-IP throttle (routes/web.php) plus a per-account lockout after N consecutive
 * failures (Settings → Security). "Locked" and "deactivated" are only revealed to someone who
 * entered the right password; everyone else gets the same generic message.
 */
class LoginController extends Controller
{
    private const GENERIC_ERROR = 'These credentials do not match our records.';

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $login = strtolower(trim($credentials['login']));
        $user = User::query()->where('email', $login)->orWhere('username', $login)->first();

        if (! $user) {
            Hash::check($credentials['password'], '$2y$12$'.str_repeat('a', 53)); // even out timing
            $this->fail($login, null);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            if (! $user->isLocked()) {
                $this->recordFailure($user);
            }
            $this->fail($login, $user);
        }

        if ($user->isLocked()) {
            Audit::log('auth.locked_attempt', "Sign-in refused for {$user->name}: account locked", $user, [], false);
            throw ValidationException::withMessages([
                'login' => 'This account is locked after too many failed sign-ins. Try again '.$user->locked_until->diffForHumans().', or ask an administrator to unlock it.',
            ]);
        }

        if (! $user->is_active) {
            Audit::log('auth.inactive_attempt', "Sign-in refused for {$user->name}: account deactivated", $user, [], false);
            throw ValidationException::withMessages(['login' => 'This account has been deactivated. Contact an administrator.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        Audit::log('auth.login', "{$user->name} signed in", $user, [], $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            Audit::log('auth.logout', "{$user->name} signed out", $user, [], $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $locked = $attempts >= Settings::int('security.max_login_attempts');

        $user->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked ? now()->addMinutes(Settings::int('security.lockout_minutes')) : null,
        ])->saveQuietly();

        if ($locked) {
            Audit::log('auth.locked', "{$user->name}'s account was locked after {$attempts} failed sign-ins", $user, [], false);
        }
    }

    private function fail(string $login, ?User $user): never
    {
        Audit::log('auth.failed', 'Failed sign-in for "'.mb_substr($login, 0, 100).'"', $user, [], false);

        throw ValidationException::withMessages(['login' => self::GENERIC_ERROR]);
    }
}
