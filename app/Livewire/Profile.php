<?php

namespace App\Livewire;

use App\Enums\NotificationType;
use App\Livewire\Concerns\Toasts;
use App\Support\Audit;
use App\Support\Palette;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile')]
class Profile extends Component
{
    use Toasts;

    public string $name = '';

    public string $email = '';

    public string $jobTitle = '';

    public string $avatarColor = 'indigo';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /** @var list<string> */
    public array $emailTypes = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->jobTitle = (string) $user->job_title;
        $this->avatarColor = $user->avatar_color;
        $this->emailTypes = $user->email_notifications ?? [];
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'avatarColor' => ['required', Rule::in(Palette::CHOICES)],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'job_title' => $data['jobTitle'] ?: null,
            'avatar_color' => $data['avatarColor'],
        ]);

        Audit::log('user.profile_updated', "{$user->name} updated their profile", $user, [], $user);
        $this->toast('Profile saved.');
    }

    public function savePassword(): void
    {
        $user = auth()->user();
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'same:passwordConfirmation'],
        ], [], ['currentPassword' => 'current password', 'password' => 'new password']);

        $user->forceFill(['password' => Hash::make($this->password)])->save();
        // Sign out every other device; keep this session.
        $user->revokeSessions(session()->getId());
        auth()->login($user);
        session()->regenerate();

        Audit::log('user.password_changed', "{$user->name} changed their password", $user, [], $user);
        $this->reset(['currentPassword', 'password', 'passwordConfirmation']);
        $this->toast('Password changed. Other devices were signed out.');
    }

    public function saveEmailPreferences(): void
    {
        $this->validate(['emailTypes' => ['array'], 'emailTypes.*' => [Rule::enum(NotificationType::class)]]);
        auth()->user()->update(['email_notifications' => array_values($this->emailTypes)]);
        $this->toast('Email preferences saved.');
    }

    public function render(): View
    {
        return view('livewire.profile', ['user' => auth()->user()]);
    }
}
