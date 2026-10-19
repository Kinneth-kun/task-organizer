<div>
    <x-page-header title="Projects" description="Long-running work with a team, a deadline and a health score.">
        <x-slot:actions>
            <a href="{{ route('export.projects') }}" class="btn btn-secondary"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New project</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-6 flex flex-wrap items-center gap-3 p-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" class="input pl-9" placeholder="Find a project…" wire:model.live.debounce.300ms="q" aria-label="Search projects">
        </div>
        <select class="input w-auto" wire:model.live="status" aria-label="Status">
            @foreach (\App\Livewire\Projects\ProjectIndex::STATUS_FILTERS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="health" aria-label="Health">
            <option value="">Any health</option>
            @foreach (\App\Enums\ProjectHealth::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="department" aria-label="Department">
            <option value="">All departments</option>
            @foreach ($this->departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" class="checkbox" wire:model.live="mine"> Only mine
        </label>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($projects as $project)
            <article class="card flex flex-col overflow-hidden border-t-4 {{ \App\Support\Palette::border($project->color) }}" wire:key="project-{{ $project->id }}">
                <div class="flex-1 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('projects.show', $project) }}" class="text-base font-semibold text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $project->name }}</a>
                        <x-status :value="$project->health" />
                    </div>
                    @if ($project->description)
                        <p class="mt-1.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $project->description }}</p>
                    @endif

                    <x-progress :value="$project->progress" :label="true" class="mt-4" height="h-2" />

                    <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex -space-x-1.5">
                            <x-avatar :user="$project->owner" title="Owner: {{ $project->owner?->name }}" />
                            @foreach ($project->members->take(4) as $member)
                                <x-avatar :user="$member" />
                            @endforeach
                            @if ($project->members->count() > 4)
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold ring-2 ring-white dark:bg-slate-800 dark:ring-slate-900">+{{ $project->members->count() - 4 }}</span>
                            @endif
                        </div>
                        <div class="text-right">
                            <p>{{ $project->open_tasks_count }} open of {{ $project->total_tasks_count }} tasks</p>
                            <p class="{{ $project->isOverdue() ? 'font-medium text-rose-600' : '' }}">{{ $project->due_date ? 'Due '.$project->due_date->format('M j, Y') : 'No due date' }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if ($project->status !== \App\Enums\ProjectStatus::Active)
                            <x-status :value="$project->status" :dot="false" />
                        @endif
                        @if ($project->department)
                            <x-badge :color="$project->department->color">{{ $project->department->name }}</x-badge>
                        @endif
                    </div>
                </div>

                {{-- Suggestions: one at a time, newest first. The key changes whenever a suggestion is
                     added or removed, so the block is rebuilt and the pager starts again at the newest
                     (updating it in place would leave several suggestions showing at once). --}}
                <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30"
                     wire:key="suggestions-{{ $project->id }}-{{ $project->suggestions->pluck('id')->join('-') }}"
                     x-data="{ i: 0, n: {{ $project->suggestions->count() }} }">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Suggestions{{ $project->suggestions->count() ? ' · '.$project->suggestions->count() : '' }}</p>
                        <div class="flex items-center gap-1">
                            <template x-if="n > 1">
                                <span class="flex items-center">
                                    <button type="button" class="icon-btn p-1" @click="i = (i - 1 + n) % n" aria-label="Previous"><x-icon name="chevron-left" class="h-3.5 w-3.5" /></button>
                                    <span class="text-[11px] text-slate-400" x-text="(i + 1) + '/' + n"></span>
                                    <button type="button" class="icon-btn p-1" @click="i = (i + 1) % n" aria-label="Next"><x-icon name="chevron-right" class="h-3.5 w-3.5" /></button>
                                </span>
                            </template>
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="openSuggestion({{ $project->id }})"><x-icon name="plus" class="h-3.5 w-3.5" /> Suggest</button>
                        </div>
                    </div>
                    @foreach ($project->suggestions as $index => $s)
                        <div x-show="i === {{ $index }}" @if ($index > 0) x-cloak @endif class="mt-2" wire:key="sugg-{{ $s->id }}">
                            <p class="line-clamp-3 text-sm text-slate-700 dark:text-slate-300">“{{ $s->body }}”</p>
                            <div class="mt-1.5 flex items-center gap-2 text-xs text-slate-400">
                                <span>{{ $s->user?->name ?? 'Former user' }} · {{ $s->created_at->diffForHumans() }}</span>
                                @if ($s->attachments->isNotEmpty())
                                    <span class="inline-flex items-center gap-0.5"><x-icon name="paperclip" class="h-3 w-3" />
                                        @foreach ($s->attachments as $file)
                                            <a href="{{ $file->url() }}" target="_blank" class="hover:text-indigo-600">{{ \Illuminate\Support\Str::limit($file->original_name, 18) }}</a>@if (! $loop->last), @endif
                                        @endforeach
                                    </span>
                                @endif
                                @can('delete', $s)
                                    <button type="button" class="ml-auto hover:text-rose-600" wire:click="deleteSuggestion({{ $s->id }})" wire:confirm="Remove this suggestion?">Remove</button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                    @if ($project->suggestions->isEmpty())
                        <p class="mt-1 text-xs text-slate-400">No suggestions yet — share an idea with the team.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="card md:col-span-2 xl:col-span-3">
                <x-empty icon="folder" title="No projects found" description="Projects you own, created or belong to appear here.">
                    @can('create', \App\Models\Project::class)
                        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> New project</a>
                    @endcan
                </x-empty>
            </div>
        @endforelse
    </div>

    @if ($suggestProject)
        <x-modal :title="'Suggestion for '.$suggestProject->name" close="$set('suggestFor', null)">
            <form wire:submit="saveSuggestion" id="suggestion-form" class="space-y-4">
                <div>
                    <label class="label" for="suggestion">Your recommendation</label>
                    <textarea id="suggestion" rows="4" class="input" wire:model="suggestion" placeholder="An idea, a risk you've spotted, a better way to do something…" autofocus></textarea>
                    @error('suggestion') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reference files <span class="font-normal text-slate-400">(optional, up to 5)</span></label>
                    <input type="file" multiple wire:model="suggestionFiles" accept="{{ \App\Services\AttachmentService::accept() }}" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm dark:file:bg-slate-800 dark:file:text-slate-300">
                    @error('suggestionFiles.*') <p class="error">{{ $message }}</p> @enderror
                </div>
                <p class="hint">The project owner and managers are notified.</p>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="$set('suggestFor', null)">Cancel</button>
                <button type="submit" form="suggestion-form" class="btn btn-primary" wire:loading.attr="disabled">Share suggestion</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
