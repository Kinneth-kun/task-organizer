<div>
    <x-page-header title="Profile & preferences" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6 text-center">
            <x-avatar :user="$user" size="h-20 w-20 text-2xl" class="mx-auto" />
            <p class="mt-3 text-lg font-semibold">{{ $user->name }}</p>
            <p class="text-sm text-slate-500">{{ '@'.$user->username }}</p>
            <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                <x-status :value="$user->role" :dot="false" />
                @if ($user->department)
                    <x-badge :color="$user->department->color">{{ $user->department->name }}</x-badge>
                @endif
            </div>
            <p class="mt-4 text-xs text-slate-400">Last sign-in {{ $user->last_login_at?->diffForHumans() ?? 'never' }}</p>
            <p class="mt-1 text-xs text-slate-400">Role and department are managed by an administrator.</p>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form wire:submit="saveProfile" class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">Name</label>
                        <input id="name" class="input" wire:model="name">
                        @error('name') <p class="error">{{ $message }}</p> @enderror
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
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Save details</button></div>
            </form>

            <form wire:submit="savePassword" class="card">
                <div class="card-header"><h2 class="card-title">Change password</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="current">Current password</label>
                        <input id="current" type="password" class="input" wire:model="currentPassword" autocomplete="current-password">
                        @error('currentPassword') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="new">New password</label>
                        <input id="new" type="password" class="input" wire:model="password" autocomplete="new-password">
                        @error('password') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="confirm">Confirm new password</label>
                        <input id="confirm" type="password" class="input" wire:model="passwordConfirmation" autocomplete="new-password">
                    </div>
                    <p class="hint sm:col-span-3">At least 8 characters with letters and numbers. Other devices are signed out.</p>
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Change password</button></div>
            </form>

            <form wire:submit="saveEmailPreferences" class="card" id="email">
                <div class="card-header">
                    <h2 class="card-title">Email me about</h2>
                    <span class="text-xs text-slate-400">In-app notifications always arrive</span>
                </div>
                <div class="card-body grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\NotificationType::cases() as $type)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                            <input type="checkbox" class="checkbox" value="{{ $type->value }}" wire:model="emailTypes">
                            <x-icon :name="$type->icon()" class="h-4 w-4 {{ \App\Support\Palette::text($type->color()) }}" /> {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Save preferences</button></div>
            </form>
        </div>
    </div>
</div>
