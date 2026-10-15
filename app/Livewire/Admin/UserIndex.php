<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class UserIndex extends Component
{
    use Toasts, WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $department = '';

    /** active | inactive | locked | '' */
    #[Url]
    public string $state = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function deactivate(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        if ($user->id === auth()->id()) {
            $this->toast('You can\'t deactivate your own account.', 'error');

            return;
        }
        $users->deactivate($user, auth()->user());
        $this->toast("{$user->name} was deactivated and signed out.", 'info');
    }

    public function activate(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        $users->activate($user, auth()->user());
        $this->toast("{$user->name} can sign in again.");
    }

    public function unlock(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        $users->unlock($user, auth()->user());
        $this->toast("{$user->name} was unlocked.");
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);
        $term = trim($this->q);

        $users = User::query()->with('department')
            ->withCount(['assignedTasks as open_tasks_count' => fn ($q) => $q->open()])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($this->role, fn ($q) => $q->where('role', $this->role))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->state === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->state === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->state === 'locked', fn ($q) => $q->where('locked_until', '>', now()))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.user-index', ['users' => $users]);
    }
}
