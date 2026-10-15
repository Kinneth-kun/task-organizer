<div>
    <x-page-header title="Users" description="Accounts, roles and departments. Deactivating someone signs them out immediately.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="h-4 w-4" /> New user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4 flex flex-wrap items-center gap-3 p-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" class="input pl-9" placeholder="Name, username or email…" wire:model.live.debounce.300ms="q" aria-label="Search users">
        </div>
        <select class="input w-auto" wire:model.live="role" aria-label="Role">
            <option value="">All roles</option>
            @foreach (\App\Enums\UserRole::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="department" aria-label="Department">
            <option value="">All departments</option>
            @foreach ($this->departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="state" aria-label="Status">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="inactive">Deactivated</option>
            <option value="locked">Locked</option>
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>Person</th><th>Role</th><th>Department</th><th>Open tasks</th><th>Last sign-in</th><th>Status</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <x-avatar :user="$u" size="h-8 w-8 text-xs" />
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900 dark:text-white">{{ $u->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ '@'.$u->username }} · {{ $u->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td><x-status :value="$u->role" :dot="false" /></td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $u->department?->name ?? '—' }}</td>
                        <td class="tabular-nums">{{ $u->open_tasks_count }}</td>
                        <td class="text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>
                            @if (! $u->is_active)
                                <x-badge color="zinc">Deactivated</x-badge>
                            @elseif ($u->isLocked())
                                <x-badge color="amber" title="Until {{ $u->locked_until->format('M j g:i A') }}"><x-icon name="lock" class="h-3 w-3" /> Locked</x-badge>
                            @else
                                <x-badge color="emerald" :dot="true">Active</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex justify-end gap-1">
                                @if ($u->isLocked())
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="unlock({{ $u->id }})"><x-icon name="unlock" class="h-3.5 w-3.5" /> Unlock</button>
                                @endif
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="h-3.5 w-3.5" /> Edit</a>
                                @if ($u->is_active)
                                    @if ($u->id !== auth()->id())
                                        <button type="button" class="btn btn-ghost btn-sm text-rose-600" wire:click="deactivate({{ $u->id }})" wire:confirm="Deactivate {{ $u->name }}? They are signed out everywhere and can't sign in until reactivated.">Deactivate</button>
                                    @endif
                                @else
                                    <button type="button" class="btn btn-ghost btn-sm text-emerald-600" wire:click="activate({{ $u->id }})">Reactivate</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="users" title="No users match" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
