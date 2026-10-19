<div>
    @php
        $user = auth()->user();
        $day = $this->day();
        $s = $this->summary;
        $label = $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l'));
    @endphp

    <x-page-header title="Daily report" :description="'Every update posted on '.$day->format('l, F j, Y').', grouped by project, for the daily meeting.'">
        <x-slot:actions>
            <a href="{{ route('export.daily', array_filter(['date' => $day->toDateString(), 'state' => $state, 'department' => $department])) }}" class="btn btn-secondary print:hidden"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
            <button type="button" class="btn btn-secondary print:hidden" onclick="window.print()"><x-icon name="document" class="h-4 w-4" /> Print</button>
            @if ($s['reportable'] > 0)
                <button type="button" class="btn btn-primary print:hidden" wire:click="markAllReported(null)"
                        wire:confirm="Mark {{ $s['reportable'] }} {{ str('update')->plural($s['reportable']) }} as reported? They will show as covered in the daily meeting, and each author is notified.">
                    <x-icon name="check" class="h-4 w-4" /> Mark all reported
                </button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Day and filters --}}
    <div class="card mb-4 flex flex-wrap items-center gap-3 p-3 print:hidden">
        <div class="flex items-center gap-1">
            <button type="button" class="icon-btn" wire:click="previousDay" aria-label="Previous day"><x-icon name="chevron-left" class="h-5 w-5" /></button>
            <div class="w-44 text-center">
                <p class="text-base font-semibold leading-tight">{{ $label }}</p>
                <p class="text-xs text-slate-500">{{ $day->format('M j, Y') }}</p>
            </div>
            <button type="button" class="icon-btn disabled:opacity-30" wire:click="nextDay" aria-label="Next day" @disabled($day->isToday())><x-icon name="chevron-right" class="h-5 w-5" /></button>
            @unless ($day->isToday())
                <button type="button" class="btn btn-secondary btn-sm ml-2" wire:click="today">Today</button>
            @endunless
            <input type="date" class="input input-sm ml-2 w-auto" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}" wire:change="$set('date', $event.target.value)" aria-label="Pick a day" wire:key="day-{{ $day->toDateString() }}">
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-3">
            <select class="input w-auto" wire:model.live="state" aria-label="Reported">
                @foreach (\App\Support\DailyReport::STATES as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            </select>
            @if ($user->isSupervisor())
                <select class="input w-auto" wire:model.live="department" aria-label="Department">
                    <option value="">All departments I can see</option>
                    @foreach ($this->departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    @php($backlog = $this->backlog)
    @if ($backlog['count'] > 0)
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <x-icon name="alert" class="h-4 w-4" />
            <span>{{ $backlog['count'] }} {{ str('update')->plural($backlog['count']) }} from earlier days {{ $backlog['count'] === 1 ? 'is' : 'are' }} still not reported.</span>
            <button type="button" class="font-medium underline hover:no-underline" wire:click="$set('date', '{{ $backlog['oldest']->toDateString() }}')">Go to {{ $backlog['oldest']->format('M j') }}</button>
        </div>
    @endif

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Updates" :value="$s['total']" icon="chat" color="indigo" />
        <x-stat label="Not reported yet" :value="$s['pending']" icon="clock" color="amber" />
        <x-stat label="Reported" :value="$s['reported']" icon="check-circle" color="emerald" />
        <x-stat label="People · projects" :value="$s['people'].' · '.$s['projects']" icon="users" color="sky" />
    </div>

    {{-- Updates by project --}}
    <div class="space-y-5" wire:loading.class="opacity-60">
        @forelse ($this->groups as $project => $remarks)
            @php($pending = $remarks->filter(fn ($r) => ! $r->isReviewed() && $user->can('review', $r))->count())
            <section class="card break-inside-avoid" wire:key="group-{{ md5($project) }}">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2">
                        <x-icon :name="$project === \App\Support\DailyReport::STANDALONE ? 'tasks' : 'folder'" class="h-4 w-4 text-slate-400" />
                        @if ($remarks->first()->task?->project)
                            <a href="{{ route('projects.show', $remarks->first()->task->project) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $project }}</a>
                        @else
                            {{ $project }}
                        @endif
                        <span class="text-xs font-normal text-slate-400">{{ $remarks->count() }} {{ str('update')->plural($remarks->count()) }}</span>
                    </h2>
                    @if ($pending > 1)
                        <button type="button" class="btn btn-ghost btn-sm print:hidden" wire:click="markAllReported(@js($project))"
                                wire:confirm="Mark {{ $pending }} updates in {{ $project }} as reported?">
                            <x-icon name="check" class="h-3.5 w-3.5" /> Mark project reported
                        </button>
                    @endif
                </div>
                <div class="divider">
                    @foreach ($remarks as $remark)
                        @php($change = \App\Support\DailyReport::describeChange($this->changes->get($remark->id)))
                        <div class="flex gap-3 px-5 py-4" wire:key="update-{{ $remark->id }}">
                            <x-avatar :user="$remark->user" size="h-8 w-8 text-xs" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $remark->user->name }}</span>
                                    on <a href="{{ route('tasks.show', $remark->task) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $remark->task->title }}</a>
                                    @if ($remark->task->parent)
                                        <span class="text-xs text-slate-400">in {{ $remark->task->parent->title }}</span>
                                    @endif
                                    <span class="text-xs text-slate-400">· {{ $remark->created_at->format('g:i A') }}</span>
                                </p>
                                @if ($change)
                                    <p class="mt-1 inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="trending-up" class="h-3.5 w-3.5" /> {{ $change }}</p>
                                @endif
                                <div class="prose-text mt-1">{{ \App\Support\Text::render($remark->body) }}</div>
                                @if ($remark->attachments->isNotEmpty())
                                    <div class="mt-2"><x-attachment-list :attachments="$remark->attachments" /></div>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($remark->isReviewed())
                                    <x-badge color="emerald" :title="'Reported by '.$remark->reviewer?->name.' · '.$remark->reviewed_at->format('M j, g:i A')"><x-icon name="check" class="h-3 w-3" /> Reported</x-badge>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ $remark->reviewer?->name }}</p>
                                @elseif ($user->can('review', $remark))
                                    <button type="button" class="btn btn-secondary btn-sm print:hidden" wire:click="markReported({{ $remark->id }})"><x-icon name="clipboard" class="h-3.5 w-3.5" /> Mark reported</button>
                                    <span class="hidden print:inline"><x-badge color="amber">Not reported</x-badge></span>
                                @else
                                    <x-badge color="amber">Not reported</x-badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="card">
                <x-empty icon="clipboard"
                         :title="$state === 'pending' && $s['total'] > 0 ? 'Everything has been reported' : 'No updates on this day'"
                         :description="$state === 'pending' && $s['total'] > 0 ? 'All of the day\'s updates have been covered.' : 'Updates appear here when someone posts a progress update with a remark on a task you can see.'" />
            </div>
        @endforelse
    </div>
</div>
