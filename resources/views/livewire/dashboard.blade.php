<div>
    @php
        $user = auth()->user();
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    @endphp

    <x-page-header :title="$greeting.', '.$user->firstName()" :description="now()->format('l, F j, Y')">
        <x-slot:actions>
            @if ($user->isSupervisor())
                <select class="input w-auto" wire:model.live="department" aria-label="Department">
                    <option value="">All departments I can see</option>
                    @foreach ($this->departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            @endif
            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-secondary"><x-icon name="folder" class="h-4 w-4" /> New project</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- My working session --}}
    @if ($this->mySession)
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span>
            <p class="flex-1 text-sm text-emerald-800 dark:text-emerald-200">
                You're working on <a href="{{ route('tasks.show', $this->mySession->task) }}" class="font-semibold underline decoration-emerald-300">{{ $this->mySession->task->title }}</a>
                · since {{ $this->mySession->started_at->format('g:i A') }}
            </p>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="stopWorking"><x-icon name="stop" class="h-4 w-4" /> Stop</button>
        </div>
    @endif

    {{-- Stats --}}
    @php($s = $this->stats)
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 {{ isset($s['unreviewed']) ? 'xl:grid-cols-6' : '' }}">
        <x-stat label="My open tasks" :value="$s['open']" icon="tasks" color="indigo" :href="route('tasks.index', ['view' => 'mine', 'status' => 'open'])" />
        <x-stat label="Overdue" :value="$s['overdue']" icon="alert" color="red" :href="route('tasks.index', ['view' => 'mine', 'due' => 'overdue'])" />
        <x-stat label="Due today" :value="$s['today']" icon="clock" color="amber" :href="route('tasks.index', ['view' => 'mine', 'due' => 'today'])" />
        <x-stat label="Done this week" :value="$s['done_week']" icon="check-circle" color="emerald" :href="route('tasks.index', ['view' => 'mine', 'status' => 'completed'])" />
        @isset($s['unreviewed'])
            <x-stat label="Updates to report" :value="$s['unreviewed']" icon="clipboard" color="violet" :href="route('reports.daily', ['state' => 'pending'])" />
            <x-stat label="Working right now" :value="$s['working']" icon="bolt" color="sky" />
        @endisset
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Focus of the Day --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2"><x-icon name="star" class="h-4 w-4 text-amber-500" /> Focus of the Day</h2>
                    <span class="text-xs text-slate-400">Pinned ☆ tasks, then overdue and due today</span>
                </div>
                <div class="divider">
                    @forelse ($this->focus as $task)
                        <x-task-row :task="$task" wire:key="focus-{{ $task->id }}">
                            <x-slot:actions>
                                <span class="hidden text-[11px] font-medium uppercase tracking-wide lg:inline {{ match ($task->focus_reason) { 'pinned' => 'text-amber-500', 'overdue' => 'text-rose-500', default => 'text-sky-500' } }}">
                                    {{ match ($task->focus_reason) { 'pinned' => 'Pinned', 'overdue' => 'Overdue', default => 'Today' } }}
                                </span>
                                <div class="flex shrink-0 items-center opacity-60 group-hover:opacity-100">
                                    @if ($task->focus_reason === 'pinned')
                                        <button type="button" class="icon-btn" wire:click="unpin({{ $task->id }})" title="Unpin"><x-icon name="star" class="h-4 w-4 fill-amber-400 text-amber-500" /></button>
                                    @endif
                                    <button type="button" class="icon-btn" wire:click="dismissFocus({{ $task->id }})" title="Not today"><x-icon name="eye-slash" class="h-4 w-4" /></button>
                                </div>
                            </x-slot:actions>
                        </x-task-row>
                    @empty
                        <x-empty icon="star" title="Nothing pressing today" description="Pin tasks with ☆ on a task page to keep them here. Overdue and due-today work shows up automatically." />
                    @endforelse
                </div>
            </section>

            {{-- My open work --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">My open work</h2>
                    <a href="{{ route('tasks.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">All my tasks →</a>
                </div>
                <div class="divider">
                    @forelse ($this->myWork as $task)
                        <x-task-row :task="$task" wire:key="work-{{ $task->id }}" />
                    @empty
                        <x-empty icon="check-circle" title="No open tasks assigned to you" />
                    @endforelse
                </div>
            </section>

            {{-- Latest updates, by project --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Latest updates</h2>
                    <a href="{{ route('reports.daily') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Daily report →</a>
                </div>
                <div class="divider">
                    @forelse (\App\Support\DailyReport::byProject($this->remarks) as $project => $remarks)
                        <p class="flex items-center gap-1.5 bg-slate-50/70 px-5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/40 dark:text-slate-400" wire:key="remark-group-{{ md5($project) }}">
                            <x-icon :name="$project === \App\Support\DailyReport::STANDALONE ? 'tasks' : 'folder'" class="h-3.5 w-3.5" /> {{ $project }}
                        </p>
                        @foreach ($remarks as $remark)
                        <div class="flex gap-3 px-5 py-4" wire:key="remark-{{ $remark->id }}">
                            <x-avatar :user="$remark->user" size="h-8 w-8 text-xs" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $remark->user->name }}</span>
                                    on <a href="{{ route('tasks.show', $remark->task) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $remark->task->title }}</a>
                                    <span class="text-xs text-slate-400">· {{ $remark->created_at->diffForHumans() }}</span>
                                </p>
                                <div class="prose-text mt-1">{{ \App\Support\Text::render(\Illuminate\Support\Str::limit($remark->body, 280)) }}</div>
                                @if ($remark->attachments->isNotEmpty())
                                    <p class="mt-1.5 inline-flex items-center gap-1 text-xs text-slate-500"><x-icon name="paperclip" class="h-3.5 w-3.5" /> {{ $remark->attachments->count() }} proof {{ str('file')->plural($remark->attachments->count()) }}</p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                @if ($remark->isReviewed())
                                    <x-badge color="emerald" title="Reported by {{ $remark->reviewer?->name }}"><x-icon name="check" class="h-3 w-3" /> Reported</x-badge>
                                @elseif ($user->can('review', $remark))
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="reviewRemark({{ $remark->id }})"><x-icon name="clipboard" class="h-3.5 w-3.5" /> Mark reported</button>
                                @else
                                    <x-badge color="amber">Not reported</x-badge>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    @empty
                        <x-empty icon="chat" title="No updates yet" description="Updates posted with a remark appear here, and in the daily report." />
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            {{-- Currently working --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2"><x-icon name="bolt" class="h-4 w-4 text-sky-500" /> Currently working</h2>
                    <span class="text-xs text-slate-400">{{ $this->working->count() }} {{ str('person')->plural($this->working->count()) }}</span>
                </div>
                <div class="p-2">
                    @forelse ($this->working->groupBy(fn ($s) => $s->task->project?->name ?? 'Standalone tasks') as $group => $sessions)
                        <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $group }}</p>
                        @foreach ($sessions as $session)
                            <a href="{{ route('tasks.show', $session->task) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50" wire:key="session-{{ $session->id }}">
                                <x-avatar :user="$session->user" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $session->user->name }}</p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $session->task->title }}</p>
                                </div>
                                <span class="text-[11px] whitespace-nowrap text-slate-400">{{ $session->started_at->diffForHumans(short: true) }}</span>
                            </a>
                        @endforeach
                    @empty
                        <x-empty icon="bolt" title="Nobody has started a task" description="Press “Start working” on a task to show up here." class="py-6" />
                    @endforelse
                </div>
            </section>

            {{-- Projects needing attention --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Needs attention</h2>
                    <a href="{{ route('projects.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Projects →</a>
                </div>
                <div class="divider">
                    @forelse ($this->attention as $project)
                        <a href="{{ route('projects.show', $project) }}" class="block px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40" wire:key="attn-{{ $project->id }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $project->name }}</p>
                                <x-status :value="$project->health" />
                            </div>
                            <x-progress :value="$project->progress" :label="true" class="mt-2" />
                            <p class="mt-1 text-xs text-slate-500">{{ $project->owner?->name }}{{ $project->due_date ? ' · due '.$project->due_date->format('M j') : '' }}</p>
                        </a>
                    @empty
                        <x-empty icon="check-circle" title="All projects on track" class="py-6" />
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
