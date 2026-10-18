{{-- One task in a list. Expects project, assignee and parent eager-loaded. --}}
@props(['task', 'showProject' => true, 'number' => null, 'indent' => 0, 'actions' => null])
@php
    $overdue = $task->isOverdue();
    $due = $task->dueLabel();
@endphp
<div {{ $attributes->merge(['class' => 'group flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40']) }}
     @if ($indent) style="padding-left: {{ 1 + $indent * 1.5 }}rem" @endif>
    @if ($indent)
        <x-icon name="corner-down-right" class="h-4 w-4 text-slate-300 dark:text-slate-600" />
    @endif
    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ \App\Support\Palette::dot($task->status->color()) }}" title="{{ $task->status->label() }}"></span>
    <div class="min-w-0 flex-1">
        <a href="{{ route('tasks.show', $task) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400 {{ $task->status === \App\Enums\TaskStatus::Completed || $task->status === \App\Enums\TaskStatus::Cancelled ? 'line-through decoration-slate-300 dark:decoration-slate-600' : '' }}">
            @if ($number)<span class="mr-1 font-mono text-xs text-slate-400">{{ $number }}</span>@endif{{ $task->title }}
        </a>
        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400">
            @if ($showProject)
                @if ($task->project)
                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full {{ \App\Support\Palette::dot($task->project->color) }}"></span>{{ $task->project->name }}</span>
                @else
                    <span>Standalone</span>
                @endif
            @endif
            @if ($task->parent && ! $indent)
                <span class="truncate">↳ {{ $task->parent->title }}</span>
            @endif
            @if ($task->category)
                <x-status :value="$task->category" :dot="false" />
            @endif
            @if (in_array($task->priority, [\App\Enums\Priority::High, \App\Enums\Priority::Urgent], true))
                <span class="{{ \App\Support\Palette::text($task->priority->color()) }} font-medium">{{ $task->priority->label() }}</span>
            @endif
            @if ($due)
                <span class="{{ $overdue || $task->due_date->isToday() ? 'font-medium text-slate-700 dark:text-slate-200' : '' }}">{{ $due }}</span>
            @elseif ($task->due_date)
                <span>{{ $task->due_date->format('M j') }}</span>
            @endif
        </div>
    </div>
    <div class="hidden w-28 sm:block"><x-progress :value="$task->progress" :label="true" /></div>
    <x-status :value="$task->status" class="hidden md:inline-flex" />
    <x-avatar :user="$task->assignee" />
    {{ $actions }}
</div>
