<div>
    <x-page-header title="Search" description="Projects, tasks and comments you have access to." />

    <div class="relative mb-6 max-w-2xl">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 h-5 w-5 -translate-y-1/2 text-slate-400" />
        <input type="search" class="input py-3 pl-11 text-base" placeholder="Type at least two characters…" wire:model.live.debounce.300ms="q" autofocus aria-label="Search">
    </div>

    @if (mb_strlen($term) < 2)
        <div class="card"><x-empty icon="search" title="Search everything you can see" description="Task titles and descriptions, latest remarks, project names and comments." /></div>
    @elseif ($results['projects']->isEmpty() && $results['tasks']->isEmpty() && $results['comments']->isEmpty())
        <div class="card"><x-empty icon="search" title="No results for “{{ $term }}”" /></div>
    @else
        <div class="space-y-6" wire:loading.class="opacity-60">
            @if ($results['projects']->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Projects</h2><span class="text-xs text-slate-400">{{ $results['projects']->count() }}</span></div>
                    <div class="divider">
                        @foreach ($results['projects'] as $project)
                            <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <span class="h-2.5 w-2.5 rounded-full {{ \App\Support\Palette::dot($project->color) }}"></span>
                                <span class="flex-1 text-sm font-medium">{{ $project->name }}</span>
                                <x-status :value="$project->health" />
                                <span class="text-xs text-slate-400">{{ $project->owner?->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($results['tasks']->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Tasks</h2><span class="text-xs text-slate-400">{{ $results['tasks']->count() }}</span></div>
                    <div class="divider">
                        @foreach ($results['tasks'] as $task)
                            <x-task-row :task="$task" wire:key="s-task-{{ $task->id }}" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($results['comments']->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Comments</h2><span class="text-xs text-slate-400">{{ $results['comments']->count() }}</span></div>
                    <div class="divider">
                        @foreach ($results['comments'] as $comment)
                            <a href="{{ route('tasks.show', ['task' => $comment->task, 'tab' => 'comments']) }}" class="flex gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <x-avatar :user="$comment->user" />
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-500"><span class="font-medium text-slate-700 dark:text-slate-200">{{ $comment->user->name }}</span> on {{ $comment->task->title }} · {{ $comment->created_at->diffForHumans() }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-sm text-slate-700 dark:text-slate-300">{{ $comment->body }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
</div>
