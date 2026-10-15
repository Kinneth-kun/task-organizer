@props(['title', 'description' => null, 'back' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Back
            </a>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
