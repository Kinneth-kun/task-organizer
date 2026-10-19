<div>
    <x-page-header :title="$user ? 'Edit '.$user->name : 'New user'" :back="route('admin.users.index')" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="card space-y-5 p-6 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Full name</label>
                    <input id="name" class="input" wire:model="name" autofocus>
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="username">Username</label>
                    <input id="username" class="input" wire:model="username" placeholder="e.g. juan.delacruz">
                    @error('username') <p class="error">{{ $message }}</p> @enderror
                    <p class="hint">Used to sign in and for @mentions.</p>
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" class="input" wire:model="email">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="job">Job title</label>
                    <input id="job" class="input" wire:model="jobTitle">
                </div>
                <div>
                    <label class="label" for="dept">Department</label>
                    <select id="dept" class="input" wire:model="departmentId">
                        <option value="">None</option>
                        @foreach ($this->departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}{{ $d->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                    @error('departmentId') <p class="error">{{ $message }}</p> @enderror
                    <p class="hint">A task's department follows its assignee.</p>
                </div>
                <div>
                    <label class="label" for="password">{{ $user ? 'Reset password' : 'Password' }}</label>
                    <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password" placeholder="{{ $user ? 'Leave blank to keep the current one' : '' }}">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <span class="label">Avatar colour</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Support\Palette::CHOICES as $choice)
                        <button type="button" wire:click="$set('avatarColor', '{{ $choice }}')" aria-label="{{ $choice }}"
                                class="h-7 w-7 rounded-full {{ \App\Support\Palette::dot($choice) }} {{ $avatarColor === $choice ? 'ring-2 ring-offset-2 ring-slate-400 dark:ring-offset-slate-900' : '' }}"></button>
                    @endforeach
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" class="checkbox" wire:model="isActive" @disabled($user && $user->id === auth()->id())> Account is active
            </label>
        </div>

        <div class="space-y-4">
            <div class="card p-6">
                <span class="label">Role</span>
                <div class="space-y-2">
                    @foreach (\App\Enums\UserRole::cases() as $option)
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3 {{ $role === $option->value ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-500/10' : 'border-slate-200 dark:border-slate-700' }}">
                            <input type="radio" class="mt-1" value="{{ $option->value }}" wire:model.live="role">
                            <span>
                                <span class="block text-sm font-medium">{{ $option->label() }}</span>
                                <span class="block text-xs text-slate-500">{{ $option->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('role') <p class="error">{{ $message }}</p> @enderror
                @if (in_array($role, ['admin', 'executive']))
                    <p class="hint mt-3">Administrators and executives already see everything, so they are never assigned tasks or added to projects.</p>
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $user ? 'Save user' : 'Create user' }}</button>
            </div>
        </div>
    </form>
</div>
