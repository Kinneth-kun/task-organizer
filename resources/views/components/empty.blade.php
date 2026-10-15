@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center']) }}>
    <div class="mb-3 rounded-full bg-slate-100 p-3 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
