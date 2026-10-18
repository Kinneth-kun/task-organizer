<div>
    @php
        $s = $this->stats;
        $dist = $this->distribution;
        $total = max(1, $dist->sum());
    @endphp

    <x-page-header title="Executive dashboard" :description="\App\Support\Settings::string('general.organization').($selected ? ' · '.$selected->name : ' · all departments')">
        <x-slot:actions>
            <select class="input w-auto" wire:model.live="department" aria-label="Department">
                <option value="">All departments</option>
                @foreach ($this->byDepartment as $row)
                    <option value="{{ $row['department']->id }}">{{ $row['department']->name }}</option>
                @endforeach
            </select>
            <a href="{{ route('export.projects') }}" class="btn btn-secondary"><x-icon name="download" class="h-4 w-4" /> Portfolio CSV</a>
            @can('admin')
                <a href="{{ route('meeting') }}" target="_blank" class="btn btn-primary"><x-icon name="presentation" class="h-4 w-4" /> Meeting mode</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6" wire:loading.class="opacity-60">
        <x-stat label="Active projects" :value="$s['projects']" icon="folder" color="indigo" />
        <x-stat label="Open tasks" :value="$s['open']" icon="tasks" color="sky" />
        <x-stat label="Overdue" :value="$s['overdue']" icon="alert" color="red" />
        <x-stat label="Completed (30 days)" :value="$s['completed30']" icon="check-circle" color="emerald" />
        <x-stat label="Working right now" :value="$s['working']" icon="bolt" color="amber" />
        <x-stat label="Remarks to review" :value="$s['unreviewed']" icon="eye" color="violet" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Portfolio health</h2><span class="text-xs text-slate-400">Active and on-hold projects</span></div>
            <div class="card-body space-y-3">
                @foreach (\App\Enums\ProjectHealth::cases() as $h)
                    @php($count = $s['health'][$h->value])
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm">{{ $h->label() }}</span>
                        <div class="h-6 flex-1 overflow-hidden rounded-md bg-slate-100 dark:bg-slate-800">
                            <div class="flex h-6 items-center rounded-md px-2 text-xs font-semibold text-white {{ \App\Support\Palette::dot($h->color()) }}" style="width: {{ $s['projects'] ? max($count ? 8 : 0, round($count / $s['projects'] * 100)) : 0 }}%">{{ $count ?: '' }}</div>
                        </div>
                        <span class="w-10 text-right text-xs text-slate-500 tabular-nums">{{ $s['projects'] ? round($count / $s['projects'] * 100) : 0 }}%</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Task distribution</h2><span class="text-xs text-slate-400">{{ $dist->sum() }} tasks</span></div>
            <div class="card-body">
                <div class="flex h-4 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    @foreach (\App\Enums\TaskStatus::cases() as $st)
                        @if ($dist[$st->value])
                            <div class="border-r border-white last:border-r-0 dark:border-slate-900 {{ \App\Support\Palette::dot($st->color()) }}" style="width: {{ $dist[$st->value] / $total * 100 }}%" title="{{ $st->label() }}: {{ $dist[$st->value] }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                    @foreach (\App\Enums\TaskStatus::cases() as $st)
                        <div class="flex items-center gap-2 text-sm">
                            <span class="h-2.5 w-2.5 rounded-full {{ \App\Support\Palette::dot($st->color()) }}"></span>
                            <span class="flex-1 text-slate-600 dark:text-slate-300">{{ $st->label() }}</span>
                            <span class="font-semibold tabular-nums">{{ $dist[$st->value] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <section class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">Work by department</h2><span class="text-xs text-slate-400">Click a department to drill down</span></div>
        <table class="table">
            <thead><tr><th>Department</th><th>People</th><th>Open</th><th>In progress</th><th>Delayed</th><th>Done (30d)</th><th>To review</th></tr></thead>
            <tbody>
                @foreach ($this->byDepartment as $row)
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ (string) $row['department']->id === $department ? 'bg-indigo-50/60 dark:bg-indigo-500/10' : '' }}"
                        wire:click="$set('department', '{{ (string) $row['department']->id === $department ? '' : $row['department']->id }}')" wire:key="dep-{{ $row['department']->id }}">
                        <td><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ \App\Support\Palette::dot($row['department']->color) }}"></span><span class="font-medium">{{ $row['department']->name }}</span></span></td>
                        <td class="tabular-nums">{{ $row['people'] }}</td>
                        <td class="tabular-nums">{{ $row['open'] }}</td>
                        <td class="tabular-nums">{{ $row['in_progress'] }}</td>
                        <td class="tabular-nums {{ $row['delayed'] ? 'font-semibold' : '' }}">{{ $row['delayed'] }}</td>
                        <td class="tabular-nums">{{ $row['done30'] }}</td>
                        <td class="tabular-nums">{{ $row['unreviewed'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if ($selected)
        <section class="card mt-6">
            <div class="card-header">
                <h2 class="card-title">{{ $selected->name }} — open work</h2>
                <a href="{{ route('tasks.index', ['view' => 'all', 'department' => $selected->id]) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Open in task list →</a>
            </div>
            <div class="divider">
                @forelse ($this->departmentTasks as $task)
                    <x-task-row :task="$task" wire:key="dt-{{ $task->id }}" />
                @empty
                    <x-empty icon="check-circle" title="No open work in {{ $selected->name }}" />
                @endforelse
            </div>
        </section>
    @endif

    <section class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">Project portfolio</h2><span class="text-xs text-slate-400">Most urgent first</span></div>
        <table class="table">
            <thead><tr><th>Project</th><th>Owner</th><th>Department</th><th class="w-48">Progress</th><th>Health</th><th>Due</th><th>Open tasks</th></tr></thead>
            <tbody>
                @forelse ($this->portfolio as $project)
                    <tr wire:key="pf-{{ $project->id }}">
                        <td><a href="{{ route('projects.show', $project) }}" class="flex items-center gap-2 font-medium hover:text-indigo-600"><span class="h-2.5 w-2.5 rounded-full {{ \App\Support\Palette::dot($project->color) }}"></span>{{ $project->name }}</a></td>
                        <td class="text-sm">{{ $project->owner?->name }}</td>
                        <td class="text-sm">{{ $project->department?->name ?? '—' }}</td>
                        <td><x-progress :value="$project->progress" :label="true" /></td>
                        <td><x-status :value="$project->health" /></td>
                        <td class="text-sm {{ $project->isOverdue() ? 'font-semibold' : '' }}">{{ $project->due_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $project->open_tasks_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="folder" title="No active projects" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
