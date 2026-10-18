<div>
    <x-page-header title="Departments" description="Every person belongs to one department; a task's department follows its assignee.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" wire:click="create"><x-icon name="plus" class="h-4 w-4" /> New department</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Department</th><th>Code</th><th>People</th><th>Projects</th><th>Open tasks</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @foreach ($departments as $d)
                    <tr wire:key="d-{{ $d->id }}">
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="h-3 w-3 rounded-full {{ \App\Support\Palette::dot($d->color) }}"></span>
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $d->name }}</p>
                                    @if ($d->description)<p class="text-xs text-slate-500">{{ $d->description }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-xs">{{ $d->code }}</td>
                        <td class="tabular-nums">{{ $d->users_count }}</td>
                        <td class="tabular-nums">{{ $d->projects_count }}</td>
                        <td class="tabular-nums">{{ $d->open_tasks_count }}</td>
                        <td>@if ($d->is_active)<x-badge color="emerald" :dot="true">Active</x-badge>@else<x-badge color="zinc">Inactive</x-badge>@endif</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $d->id }})"><x-icon name="pencil" class="h-3.5 w-3.5" /> Edit</button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleActive({{ $d->id }})">{{ $d->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                @if (! $d->users_count && ! $d->projects_count)
                                    <button type="button" class="icon-btn hover:text-rose-600" wire:click="delete({{ $d->id }})" wire:confirm="Delete {{ $d->name }}?" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($editing)
        <x-modal :title="$editId ? 'Edit department' : 'New department'" close="$set('editing', false)">
            <form wire:submit="save" id="dept-form" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="label" for="d-name">Name</label>
                        <input id="d-name" class="input" wire:model="name" autofocus>
                        @error('name') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="d-code">Code</label>
                        <input id="d-code" class="input uppercase" wire:model="code" maxlength="10">
                        @error('code') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="label" for="d-desc">Description</label>
                    <input id="d-desc" class="input" wire:model="description">
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="$set('editing', false)">Cancel</button>
                <button type="submit" form="dept-form" class="btn btn-primary">Save</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
