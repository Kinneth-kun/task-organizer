<div>
    @php
        $user = auth()->user();
        $canUpdate = $user->can('update', $project);
        $canAddTask = $user->can('createTask', $project);
        $eval = $this->evaluation;
    @endphp

    <x-page-header :title="$project->name" :back="route('projects.index')">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-status :value="$project->health" />
                <x-status :value="$project->status" :dot="false" />
                <x-status :value="$project->priority" :dot="false" />
                @if ($project->department)
                    <x-badge :color="$project->department->color">{{ $project->department->name }}</x-badge>
                @endif
                <span class="text-xs text-slate-500">
                    Owner {{ $project->owner?->name }}
                    @if ($project->due_date) · due <span class="{{ $project->isOverdue() ? 'font-medium text-rose-600' : '' }}">{{ $project->due_date->format('M j, Y') }}</span> @endif
                </span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if ($canUpdate)
                @if ($project->isCompleted())
                    <button type="button" class="btn btn-secondary" wire:click="reopen"><x-icon name="refresh" class="h-4 w-4" /> Reopen</button>
                @else
                    <button type="button" class="btn btn-success" wire:click="complete" wire:confirm="Mark this project as completed? The team is notified, and later tasks are logged as post-launch work."><x-icon name="check-circle" class="h-4 w-4" /> Mark completed</button>
                @endif
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="btn btn-secondary" @click="open = !open"><x-icon name="dots" class="h-4 w-4" /></button>
                    <div x-show="open" x-cloak x-transition class="menu right-0">
                        <a href="{{ route('projects.edit', $project) }}" class="menu-item"><x-icon name="pencil" class="h-4 w-4" /> Edit details</a>
                        @foreach ([\App\Enums\ProjectStatus::Active, \App\Enums\ProjectStatus::OnHold, \App\Enums\ProjectStatus::Cancelled] as $status)
                            @if ($project->status !== $status)
                                <button type="button" class="menu-item" wire:click="setStatus('{{ $status->value }}')" @click="open = false"><x-icon name="flag" class="h-4 w-4" /> Set {{ $status->label() }}</button>
                            @endif
                        @endforeach
                        @can('delete', $project)
                            <button type="button" class="menu-item text-rose-600 dark:text-rose-400" wire:click="deleteProject" wire:confirm="Delete this project and its tasks? History is kept for the audit log."><x-icon name="trash" class="h-4 w-4" /> Delete project</button>
                        @endcan
                    </div>
                </div>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Summary strip --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Progress</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $project->progress }}%</p>
            <x-progress :value="$project->progress" class="mt-2" height="h-2" />
            @if ($eval['stats']['expected_progress'] >= 0)
                <p class="mt-1.5 text-xs text-slate-400">{{ $eval['stats']['expected_progress'] }}% of the schedule has elapsed</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Open tasks</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $eval['stats']['open'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $eval['stats']['completed'] }} completed of {{ $eval['stats']['total'] }} top-level</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Overdue</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums {{ $eval['stats']['overdue'] ? 'text-rose-600' : '' }}">{{ $eval['stats']['overdue'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $eval['stats']['overdue_percent'] }}% of open work</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Health</p>
            <div class="mt-1.5"><x-status :value="$project->health" /></div>
            <p class="mt-1.5 text-xs text-slate-500">{{ $eval['reasons'][0] ?? '' }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 flex gap-5 border-b border-slate-200 dark:border-slate-800">
        @foreach (['tasks' => ['Tasks', 'list'], 'board' => ['Board', 'columns'], 'team' => ['Team', 'users'], 'overview' => ['Overview', 'info']] as $key => [$label, $icon])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="tab {{ $tab === $key ? 'active' : '' }}"><x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'tasks')
        @if ($canAddTask)
            <form wire:submit="addTask" class="card mb-4 flex flex-wrap items-start gap-2 p-3">
                <div class="min-w-56 flex-1">
                    <input type="text" class="input" wire:model="newTitle" placeholder="{{ $project->isCompleted() ? 'Log post-launch work…' : 'Add a task…' }}">
                    @error('newTitle') <p class="error">{{ $message }}</p> @enderror
                </div>
                @if ($project->isCompleted())
                    <select class="input w-auto" wire:model="newCategory" aria-label="Type">
                        @foreach (\App\Enums\TaskCategory::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <select class="input w-auto" wire:model="newAssignee" aria-label="Assignee">
                    <option value="">Unassigned</option>
                    @foreach ($this->people as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
                <input type="date" class="input w-auto" wire:model="newDue" aria-label="Due date">
                <button type="submit" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add</button>
                <a href="{{ route('tasks.create', ['project' => $project->id]) }}" class="btn btn-ghost">More options</a>
            </form>
        @endif

        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">{{ $project->isCompleted() ? 'Original build' : 'Tasks' }}</h2>
                <span class="text-xs text-slate-400">{{ $buildTasks->count() }} visible</span>
            </div>
            <div class="divider">
                @forelse ($buildTasks as $task)
                    <x-task-row :task="$task" :show-project="false" :number="$task->number" :indent="$task->tree_depth" wire:key="t-{{ $task->id }}">
                        <x-slot:actions>
                            @if ($task->canHaveChildren() && $user->can('addSubtask', $task))
                                <a href="{{ route('tasks.show', ['task' => $task, 'add_subtask' => 1]) }}#subtasks" class="icon-btn opacity-0 group-hover:opacity-100" title="Add subtask"><x-icon name="plus" class="h-4 w-4" /></a>
                            @endif
                        </x-slot:actions>
                    </x-task-row>
                @empty
                    <x-empty icon="tasks" title="No tasks yet" description="Add the first task above. Break big tasks into subtasks — up to three levels — and progress rolls up automatically." />
                @endforelse
            </div>
        </section>

        @if ($project->isCompleted() || $postLaunch->isNotEmpty())
            <section class="card mt-6 overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2"><x-icon name="sparkles" class="h-4 w-4 text-violet-500" /> Enhancements & updates</h2>
                    <span class="text-xs text-slate-400">Post-launch work · not counted in progress or health</span>
                </div>
                <div class="divider">
                    @forelse ($postLaunch as $task)
                        <x-task-row :task="$task" :show-project="false" :number="$task->number" :indent="$task->tree_depth" wire:key="pl-{{ $task->id }}" />
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No post-launch work logged yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    @elseif ($tab === 'board')
        <p class="mb-3 text-xs text-slate-500">Drag cards between columns to change their status. Tasks with subtasks follow their subtasks and can't be dragged.</p>
        <div class="grid auto-cols-[minmax(15rem,1fr)] grid-flow-col gap-4 overflow-x-auto pb-4">
            @foreach ($columns as $column)
                @php($items = $this->tasks->where('status', $column))
                <div class="flex min-h-64 flex-col rounded-xl bg-slate-100/80 p-2 dark:bg-slate-900/60" wire:key="col-{{ $column->value }}">
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span class="h-2 w-2 rounded-full {{ \App\Support\Palette::dot($column->color()) }}"></span>{{ $column->label() }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $items->count() }}</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-2" wire:sort="moveTask" wire:sort:group="board" wire:sort:group-id="{{ $column->value }}">
                        @foreach ($items as $task)
                            @php($locked = $task->children_count > 0 || ! $user->can('update', $task))
                            <div wire:key="card-{{ $task->id }}" wire:sort:item="{{ $task->id }}" @if ($locked) wire:sort:ignore @endif
                                 class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 {{ $locked ? 'opacity-80' : 'cursor-grab active:cursor-grabbing' }}">
                                <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-slate-800 hover:text-indigo-600 dark:text-slate-100" wire:sort:ignore>
                                    @if ($task->number)<span class="mr-1 font-mono text-[11px] text-slate-400">{{ $task->number }}</span>@endif{{ $task->title }}
                                </a>
                                @if ($task->parent)
                                    <p class="mt-0.5 truncate text-[11px] text-slate-400">↳ {{ $task->parent->title }}</p>
                                @endif
                                <x-progress :value="$task->progress" class="mt-2" />
                                <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                    <span class="{{ $task->isOverdue() ? 'font-medium text-rose-600' : '' }}">{{ $task->due_date?->format('M j') ?? 'No date' }}</span>
                                    <div class="flex items-center gap-1.5">
                                        @if ($task->children_count)
                                            <span title="Follows its subtasks"><x-icon name="lock" class="h-3.5 w-3.5" /></span>
                                        @endif
                                        <x-avatar :user="$task->assignee" size="h-6 w-6 text-[10px]" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($tab === 'team')
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Team</h2>
                <span class="text-xs text-slate-400">Administrators and executives already see every project</span>
            </div>
            <div class="divider">
                <div class="flex items-center gap-3 px-5 py-3">
                    <x-avatar :user="$project->owner" size="h-9 w-9 text-xs" />
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ $project->owner?->name }}</p>
                        <p class="text-xs text-slate-500">{{ $project->owner?->job_title }}</p>
                    </div>
                    <x-badge color="indigo">Owner</x-badge>
                </div>
                @foreach ($project->members as $member)
                    <div class="flex items-center gap-3 px-5 py-3" wire:key="member-{{ $member->id }}">
                        <x-avatar :user="$member" size="h-9 w-9 text-xs" />
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ $member->name }}</p>
                            <p class="text-xs text-slate-500">{{ $member->job_title }}{{ $member->department ? ' · '.$member->department->name : '' }}</p>
                        </div>
                        @can('manageMembers', $project)
                            <select class="input input-sm w-auto" wire:change="changeRole({{ $member->id }}, $event.target.value)" aria-label="Role">
                                @foreach (\App\Enums\ProjectMemberRole::options() as $value => $label)
                                    <option value="{{ $value }}" @selected($member->pivot->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="icon-btn hover:text-rose-600" wire:click="removeMember({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from the project?" title="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                        @else
                            <x-badge color="slate">{{ \App\Enums\ProjectMemberRole::labelFor($member->pivot->role) }}</x-badge>
                        @endcan
                    </div>
                @endforeach
            </div>
            @can('manageMembers', $project)
                <form wire:submit="addMember" class="flex flex-wrap gap-2 border-t border-slate-100 p-4 dark:border-slate-800">
                    <select class="input min-w-56 flex-1" wire:model="memberId" aria-label="Person">
                        <option value="">Add a person…</option>
                        @foreach ($this->people as $person)
                            @continue($person->id === $project->owner_id || $project->members->contains('id', $person->id))
                            <option value="{{ $person->id }}">{{ $person->name }} — {{ $person->department?->name ?? 'No department' }}</option>
                        @endforeach
                    </select>
                    <select class="input w-auto" wire:model="memberRole" aria-label="Role">
                        @foreach (\App\Enums\ProjectMemberRole::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Add member</button>
                    @error('memberId') <p class="error w-full">{{ $message }}</p> @enderror
                </form>
            @endcan
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card card-body lg:col-span-2">
                <h2 class="card-title mb-2">About</h2>
                <div class="prose-text">{{ $project->description ? \App\Support\Text::render($project->description) : 'No description.' }}</div>
                <h2 class="card-title mt-6 mb-2">Why it's {{ $project->health->label() }}</h2>
                <ul class="space-y-1.5">
                    @foreach ($eval['reasons'] as $reason)
                        <li class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300"><x-icon name="info" class="mt-0.5 h-4 w-4 text-slate-400" /> {{ $reason }}</li>
                    @endforeach
                </ul>
            </section>
            <section class="card card-body">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Owner</dt><dd class="mt-0.5 font-medium">{{ $project->owner?->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Created by</dt><dd class="mt-0.5">{{ $project->creator?->name }} · {{ $project->created_at->format('M j, Y') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Department</dt><dd class="mt-0.5">{{ $project->department?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Schedule</dt><dd class="mt-0.5">{{ $project->start_date?->format('M j, Y') ?? '—' }} → {{ $project->due_date?->format('M j, Y') ?? '—' }}</dd></div>
                    @if ($project->completed_at)
                        <div><dt class="text-xs text-slate-500">Completed</dt><dd class="mt-0.5">{{ $project->completed_at->format('M j, Y') }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    @endif
</div>
