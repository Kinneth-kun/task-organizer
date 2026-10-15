@props(['label', 'value', 'icon' => 'chart', 'color' => 'indigo', 'href' => null, 'hint' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex items-center gap-4 p-4'.($href ? ' transition hover:border-indigo-300 hover:shadow dark:hover:border-indigo-500/50' : '')]) }}>
    <div class="rounded-lg p-2.5 {{ \App\Support\Palette::soft($color) }}">
        <x-icon :name="$icon" class="h-5 w-5" />
    </div>
    <div class="min-w-0">
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $value }}</p>
        @if ($hint)
            <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
