<div class="flex min-h-screen flex-col bg-slate-950 text-slate-100"
     x-data="{ slide: 0, count: 4, go(n) { this.slide = (n + this.count) % this.count } }"
     @keydown.window.arrow-right="go(slide + 1)" @keydown.window.arrow-left="go(slide - 1)" wire:poll.60s>
    <header class="flex items-center gap-4 border-b border-white/10 px-8 py-4">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600"><x-icon name="presentation" class="h-5 w-5" /></span>
        <div>
            <p class="text-lg font-semibold">{{ \App\Support\Settings::string('general.organization') }} · Project review</p>
            <p class="text-xs text-slate-400">{{ now()->format('l, F j, Y · g:i A') }} · refreshes every minute</p>
        </div>
        <nav class="ml-auto flex items-center gap-1 rounded-xl bg-white/5 p-1">
            @foreach (['Overview', 'Needs attention', 'Coming up', 'Recent wins'] as $i => $label)
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="slide === {{ $i }} ? 'bg-white text-slate-900' : 'text-slate-300 hover:bg-white/10'" @click="go({{ $i }})">{{ $label }}</button>
            @endforeach
        </nav>
        <button type="button" class="rounded-lg p-2 text-slate-300 hover:bg-white/10" title="Full screen" @click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"><x-icon name="expand" class="h-5 w-5" /></button>
        <a href="{{ route('executive') }}" class="rounded-lg p-2 text-slate-300 hover:bg-white/10" title="Exit"><x-icon name="x" class="h-5 w-5" /></a>
    </header>

    <main class="flex-1 overflow-y-auto px-8 py-8">
        {{-- Overview --}}
        <section x-show="slide === 0">
            <h2 class="mb-6 text-3xl font-semibold tracking-tight">Project overview <span class="text-slate-500">· {{ $projects->count() }} active</span></h2>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($projects as $project)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-lg font-semibold">{{ $project->name }}</p>
                            <x-status :value="$project->health" />
                        </div>
                        <p class="mt-1 text-sm text-slate-400">{{ $project->owner?->name }}{{ $project->department ? ' · '.$project->department->name : '' }}</p>
                        <div class="mt-4 flex items-end justify-between">
                            <span class="text-4xl font-bold tabular-nums">{{ $project->progress }}%</span>
                            <span class="text-sm text-slate-400">{{ $project->open_tasks_count }} open · {{ $project->due_date ? 'due '.$project->due_date->format('M j') : 'no due date' }}</span>
                        </div>
                        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white/10"><div class="h-2.5 rounded-full {{ \App\Support\Palette::dot($project->health->color()) }}" style="width: {{ $project->progress }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Needs attention --}}
        <section x-show="slide === 1" x-cloak class="grid gap-8 xl:grid-cols-2">
            <div>
                <h2 class="mb-5 text-3xl font-semibold tracking-tight">Projects at risk or delayed</h2>
                <div class="space-y-3">
                    @forelse ($attention as $project)
                        <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 px-5 py-4">
                            <x-status :value="$project->health" />
                            <div class="flex-1"><p class="text-lg font-medium">{{ $project->name }}</p><p class="text-sm text-slate-400">{{ $project->owner?->name }}</p></div>
                            <span class="text-2xl font-bold tabular-nums">{{ $project->progress }}%</span>
                        </div>
                    @empty
                        <p class="text-lg text-emerald-400">Every project is on track. 🎉</p>
                    @endforelse
                </div>
            </div>
            <div>
                <h2 class="mb-5 text-3xl font-semibold tracking-tight">Delayed tasks</h2>
                <div class="space-y-2">
                    @forelse ($delayedTasks as $task)
                        <div class="flex items-center gap-3 rounded-xl bg-red-500/10 px-4 py-3">
                            <x-avatar :user="$task->assignee" size="h-8 w-8 text-xs" />
                            <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $task->title }}</p><p class="truncate text-sm text-slate-400">{{ $task->project?->name ?? 'Standalone' }} · {{ $task->assignee?->name ?? 'Unassigned' }}</p></div>
                            <span class="text-sm font-medium text-red-300">{{ $task->dueLabel() ?? 'Delayed' }}</span>
                        </div>
                    @empty
                        <p class="text-lg text-emerald-400">No delayed tasks.</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Coming up --}}
        <section x-show="slide === 2" x-cloak class="grid gap-8 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <h2 class="mb-5 text-3xl font-semibold tracking-tight">Due in the next 7 days</h2>
                <div class="space-y-2">
                    @forelse ($upcoming as $task)
                        <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            <span class="w-20 text-sm font-semibold text-indigo-300">{{ $task->due_date->isToday() ? 'Today' : $task->due_date->format('D M j') }}</span>
                            <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $task->title }}</p><p class="truncate text-sm text-slate-400">{{ $task->project?->name ?? 'Standalone' }}</p></div>
                            <span class="text-sm tabular-nums text-slate-300">{{ $task->progress }}%</span>
                            <x-avatar :user="$task->assignee" size="h-8 w-8 text-xs" />
                        </div>
                    @empty
                        <p class="text-lg text-slate-400">Nothing due this week.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <h2 class="mb-5 text-3xl font-semibold tracking-tight">Project deadlines</h2>
                <div class="space-y-2">
                    @forelse ($deadlines as $project)
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            <p class="font-medium">{{ $project->name }}</p>
                            <p class="text-sm text-slate-400">{{ $project->due_date->format('l, M j') }} · {{ $project->progress }}% done</p>
                        </div>
                    @empty
                        <p class="text-lg text-slate-400">No project deadlines in the next two weeks.</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Wins --}}
        <section x-show="slide === 3" x-cloak>
            <h2 class="mb-5 text-3xl font-semibold tracking-tight">Completed in the last 7 days</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($wins as $task)
                    <div class="flex items-center gap-3 rounded-xl bg-emerald-500/10 px-4 py-3">
                        <x-icon name="check-circle" class="h-6 w-6 text-emerald-400" />
                        <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $task->title }}</p><p class="truncate text-sm text-slate-400">{{ $task->assignee?->name ?? '—' }} · {{ $task->project?->name ?? 'Standalone' }}</p></div>
                    </div>
                @empty
                    <p class="text-lg text-slate-400">No completed tasks this week yet.</p>
                @endforelse
            </div>
        </section>
    </main>

    <footer class="flex items-center justify-center gap-2 pb-5 text-xs text-slate-500">
        <button type="button" class="rounded p-1 hover:bg-white/10" @click="go(slide - 1)"><x-icon name="chevron-left" class="h-4 w-4" /></button>
        <span x-text="(slide + 1) + ' / ' + count"></span>
        <button type="button" class="rounded p-1 hover:bg-white/10" @click="go(slide + 1)"><x-icon name="chevron-right" class="h-4 w-4" /></button>
        <span class="ml-3">← → to move between sections</span>
    </footer>
</div>
