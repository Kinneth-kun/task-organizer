<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use App\Support\Palette;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UserForm extends Component
{
    use Toasts;

    public ?User $user = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $role = 'member';

    public string $departmentId = '';

    public string $jobTitle = '';

    public string $avatarColor = 'indigo';

    public bool $isActive = true;

    public string $password = '';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);
            $this->user = $user;
            $this->name = $user->name;
            $this->username = $user->username;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->departmentId = (string) ($user->department_id ?? '');
            $this->jobTitle = (string) $user->job_title;
            $this->avatarColor = $user->avatar_color;
            $this->isActive = $user->is_active;

            return;
        }

        $this->authorize('create', User::class);
        $this->user = null;
        $this->avatarColor = Palette::CHOICES[array_rand(Palette::CHOICES)];
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    public function save(UserService $users): void
    {
        $id = $this->user?->id;
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/i', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'avatarColor' => ['required', Rule::in(Palette::CHOICES)],
            'isActive' => ['boolean'],
            'password' => [$this->user ? 'nullable' : 'required', Password::defaults()],
        ], ['username.regex' => 'Use letters, numbers, dots, dashes or underscores (used for @mentions).'], ['departmentId' => 'department']);

        $values = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'department_id' => $data['departmentId'] ?: null,
            'job_title' => $data['jobTitle'] ?: null,
            'avatar_color' => $data['avatarColor'],
        ];

        $actor = auth()->user();

        if ($this->user) {
            if ($this->user->id === $actor->id && $data['role'] !== UserRole::Admin->value) {
                $this->addError('role', 'You can\'t remove your own administrator role.');

                return;
            }

            $users->update($this->user, [...$values, 'password' => $data['password'] ?: null], $actor);
            if ($this->user->is_active !== $data['isActive'] && $this->user->id !== $actor->id) {
                $data['isActive'] ? $users->activate($this->user, $actor) : $users->deactivate($this->user, $actor);
            }
            $this->flashToast('User saved.'.($data['password'] ? ' Their password was reset and they were signed out.' : ''));
        } else {
            $users->create([...$values, 'is_active' => $data['isActive'], 'password' => $data['password'], 'email_verified_at' => now()], $actor);
            $this->flashToast('User created. Share the password with them securely.');
        }

        $this->redirectRoute('admin.users.index');
    }

    public function render(): View
    {
        return view('livewire.admin.user-form')->title($this->user ? 'Edit user' : 'New user');
    }
}
