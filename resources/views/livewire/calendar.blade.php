<div>
    <x-page-header title="Calendar" description="Tasks by due date, with project deadlines." />

    <div class="card mb-4 flex flex-wrap items-center gap-3 p-3">
        <div class="flex items-center gap-1">
            <button type="button" class="icon-btn" wire:click="previous" aria-label="Previous month"><x-icon name="chevron-left" class="h-5 w-5" /></button>
            <h2 class="w-40 text-center text-base font-semibold">{{ $first->format('F Y') }}</h2>
            <button type="button" class="icon-btn" wire:click="next" aria-label="Next month"><x-icon name="chevron-right" class="h-5 w-5" /></button>
            <button type="button" class="btn btn-secondary btn-sm ml-2" wire:click="today">Today</button>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-3">
            <select class="input w-auto max-w-60" wire:model.live="project" aria-label="Project">
                <option value="">All projects & standalone</option>
                <option value="standalone">Standalone tasks only</option>
                @foreach ($this->projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="mine"> Only mine</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="done"> Show completed</label>
        </div>
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/60">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                <div class="py-2">{{ $d }}</div>
            @endforeach
        </div>
        @foreach ($weeks as $week)
            <div class="grid grid-cols-7 divide-x divide-slate-100 border-b border-slate-100 last:border-b-0 dark:divide-slate-800 dark:border-slate-800">
                @foreach ($week as $day)
                    @php
                        $key = $day->toDateString();
                        $dayTasks = $tasks[$key] ?? collect();
                        $dayDeadlines = $deadlines[$key] ?? collect();
                        $outside = $day->month !== $first->month;
                    @endphp
                    <div class="min-h-28 p-1.5 {{ $outside ? 'bg-slate-50/70 dark:bg-slate-900/40' : '' }}" wire:key="day-{{ $key }}">
                        <p class="mb-1 text-right text-xs {{ $day->isToday() ? 'font-semibold' : '' }}">
                            <span class="{{ $day->isToday() ? 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white' : ($outside ? 'text-slate-300 dark:text-slate-600' : 'text-slate-500') }}">{{ $day->day }}</span>
                        </p>
                        <div class="space-y-1">
                            @foreach ($dayDeadlines as $p)
                                <a href="{{ route('projects.show', $p) }}" class="flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[11px] font-semibold {{ \App\Support\Palette::soft($p->color) }}" title="Project deadline: {{ $p->name }}">
                                    <x-icon name="flag" class="h-3 w-3" /> {{ $p->name }}
                                </a>
                            @endforeach
                            @foreach ($dayTasks->take(4) as $t)
                                <a href="{{ route('tasks.show', $t) }}" class="flex items-center gap-1.5 truncate rounded px-1.5 py-0.5 text-[11px] hover:bg-slate-100 dark:hover:bg-slate-800 {{ $t->isOverdue() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300' }}"
                                   title="{{ $t->title }}{{ $t->project ? ' · '.$t->project->name : '' }} · {{ $t->status->label() }}">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ \App\Support\Palette::dot($t->status->color()) }}"></span>
                                    <span class="truncate {{ in_array($t->status->value, ['completed', 'cancelled']) ? 'line-through' : '' }}">{{ $t->title }}</span>
                                </a>
                            @endforeach
                            @if ($dayTasks->count() > 4)
                                <a href="{{ route('tasks.index', ['view' => 'all', 'status' => '', 'q' => '']) }}" class="block px-1.5 text-[11px] font-medium text-indigo-600">+{{ $dayTasks->count() - 4 }} more</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
