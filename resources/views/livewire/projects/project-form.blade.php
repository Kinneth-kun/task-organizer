<div>
    <x-page-header :title="$project ? 'Edit project' : 'New project'" :back="$project ? route('projects.show', $project) : route('projects.index')"
                   :description="$project ? null : 'Set up a workspace for related work, add the team, and sketch the first tasks and subtasks.'" />

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card space-y-5 p-6 lg:col-span-2">
                <div>
                    <label class="label" for="name">Project name</label>
                    <input id="name" type="text" class="input" wire:model="name" placeholder="e.g. Employee Self-Service Portal" autofocus>
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="description">Description</label>
                    <textarea id="description" rows="4" class="input" wire:model="description" placeholder="Goal, scope, who it's for…"></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="start">Start date</label>
                        <input id="start" type="date" class="input" wire:model="startDate">
                    </div>
                    <div>
                        <label class="label" for="due">Due date</label>
                        <input id="due" type="date" class="input" wire:model="dueDate">
                        @error('dueDate') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="card space-y-5 p-6">
                <div>
                    <label class="label" for="owner">Owner</label>
                    <select id="owner" class="input" wire:model="ownerId" @disabled(! $this->canPickOwner)>
                        @foreach ($this->people as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error('ownerId') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="department">Department</label>
                    <select id="department" class="input" wire:model="departmentId">
                        <option value="">None</option>
                        @foreach ($this->departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">The department's supervisor can oversee this project.</p>
                </div>
                <div>
                    <label class="label" for="priority">Priority</label>
                    <select id="priority" class="input" wire:model="priority">
                        @foreach (\App\Enums\Priority::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <span class="label">Colour</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Support\Palette::CHOICES as $choice)
                            <button type="button" wire:click="$set('color', '{{ $choice }}')" aria-label="{{ $choice }}"
                                    class="h-7 w-7 rounded-full {{ \App\Support\Palette::dot($choice) }} {{ $color === $choice ? 'ring-2 ring-offset-2 ring-slate-400 dark:ring-offset-slate-900' : '' }}"></button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @unless ($project)
            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Team --}}
                <div class="card p-6">
                    <h2 class="card-title">Team</h2>
                    <p class="hint mb-3">Members see the project and their department's tasks. Project managers can edit it and manage the team.</p>
                    <div x-data="{ q: '' }">
                        <input type="search" x-model="q" class="input input-sm mb-2" placeholder="Find people…">
                        <div class="max-h-80 space-y-0.5 overflow-y-auto">
                            @foreach ($this->assignable as $person)
                                @continue((string) $person->id === $ownerId)
                                <div class="flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-slate-50 dark:hover:bg-slate-800" wire:key="pm-{{ $person->id }}"
                                     x-show="!q || @js(strtolower($person->name)).includes(q.toLowerCase())">
                                    <input type="checkbox" class="checkbox" @checked(isset($members[$person->id])) wire:click="toggleMember({{ $person->id }})" id="m-{{ $person->id }}">
                                    <label for="m-{{ $person->id }}" class="flex flex-1 cursor-pointer items-center gap-2 text-sm">
                                        <x-avatar :user="$person" size="h-6 w-6 text-[10px]" /> {{ $person->name }}
                                        <span class="text-xs text-slate-400">{{ $person->department?->code }}</span>
                                    </label>
                                    @if (isset($members[$person->id]))
                                        <select class="input input-sm w-auto" wire:model="members.{{ $person->id }}" aria-label="Role">
                                            <option value="member">Member</option>
                                            <option value="manager">Manager</option>
                                        </select>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Task builder --}}
                <div class="card p-6 lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2 class="card-title">First tasks</h2>
                            <p class="hint">Optional. One subtask per line — they roll up into their task and take its start and end dates.</p>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow"><x-icon name="plus" class="h-4 w-4" /> Add task</button>
                    </div>
                    <div class="space-y-3">
                        @foreach ($taskRows as $i => $row)
                            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700" wire:key="row-{{ $i }}">
                                <div class="flex flex-wrap items-start gap-2">
                                    <span class="mt-2 w-6 font-mono text-xs text-slate-400">{{ $i + 1 }}</span>
                                    <input type="text" class="input min-w-48 flex-1" wire:model="taskRows.{{ $i }}.title" placeholder="Task title">
                                    <select class="input w-auto" wire:model="taskRows.{{ $i }}.assignee_id" aria-label="Assignee">
                                        <option value="">Unassigned</option>
                                        @foreach ($this->assignable as $person)
                                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" class="input w-auto" wire:model="taskRows.{{ $i }}.start_date" aria-label="Start date" title="Start date">
                                    <input type="date" class="input w-auto" wire:model="taskRows.{{ $i }}.due_date" aria-label="End date" title="End date">
                                    <button type="button" class="icon-btn mt-1" wire:click="removeRow({{ $i }})" title="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                                </div>
                                <textarea rows="2" class="input mt-2 ml-8 w-[calc(100%-2rem)] text-xs" wire:model="taskRows.{{ $i }}.subtasks" placeholder="Subtasks (optional, one per line)"></textarea>
                                @error('taskRows.'.$i.'.title') <p class="error ml-8">{{ $message }}</p> @enderror
                                @error('taskRows.'.$i.'.start_date') <p class="error ml-8">{{ $message }}</p> @enderror
                                @error('taskRows.'.$i.'.due_date') <p class="error ml-8">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endunless

        <div class="flex justify-end gap-2">
            <a href="{{ $project ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $project ? 'Save changes' : 'Create project' }}</button>
        </div>
    </form>
</div>
