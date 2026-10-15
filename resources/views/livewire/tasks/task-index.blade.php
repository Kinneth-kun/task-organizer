<div>
    <x-page-header title="My Tasks" description="Everything you're assigned to, created or collaborating on.">
        <x-slot:actions>
            <a href="{{ route('export.tasks', $this->filters()) }}" class="btn btn-secondary"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
            <a href="{{ route('tasks.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New task</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Views --}}
    <div class="mb-4 flex gap-5 overflow-x-auto border-b border-slate-200 dark:border-slate-800">
        @foreach (\App\Support\TaskFilters::VIEWS as $key => $label)
            <button type="button" wire:click="$set('view', '{{ $key }}')" class="tab {{ $view === $key ? 'active' : '' }}">
                {{ $label }}
                <span class="rounded-full bg-slate-100 px-1.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $this->viewCounts[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card mb-4 flex flex-wrap items-end gap-3 p-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" class="input pl-9" placeholder="Filter by title…" wire:model.live.debounce.300ms="q" aria-label="Search">
        </div>
        <select class="input w-auto" wire:model.live="status" aria-label="Status">
            <option value="">Any status</option>
            <option value="open">Open</option>
            @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="due" aria-label="Due">
            <option value="">Any due date</option>
            @foreach (\App\Support\TaskFilters::DUE as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="priority" aria-label="Priority">
            <option value="">Any priority</option>
            @foreach (\App\Enums\Priority::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto max-w-56" wire:model.live="project" aria-label="Project">
            <option value="">All projects</option>
            <option value="standalone">Standalone tasks</option>
            @foreach ($this->projects as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="department" aria-label="Department">
            <option value="">All departments</option>
            @foreach ($this->departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="sort" aria-label="Sort">
            <option value="due">Sort: due date</option>
            <option value="priority">Sort: priority</option>
            <option value="updated">Sort: recently updated</option>
            <option value="created">Sort: newest</option>
        </select>
        @if ($hasFilters)
            <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters"><x-icon name="x" class="h-4 w-4" /> Clear</button>
        @endif
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60">
        <div class="divider">
            @forelse ($tasks as $task)
                <x-task-row :task="$task" wire:key="task-{{ $task->id }}" />
            @empty
                <x-empty icon="tasks" title="No tasks match" description="Try another view or clear the filters.">
                    <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> New task</a>
                </x-empty>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
