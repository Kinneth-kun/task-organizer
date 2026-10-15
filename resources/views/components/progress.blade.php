@props(['value' => 0, 'color' => null, 'label' => false, 'height' => 'h-1.5'])
@php
    $value = max(0, min(100, (int) $value));
    $color ??= $value >= 100 ? 'emerald' : ($value >= 50 ? 'indigo' : ($value > 0 ? 'sky' : 'slate'));
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="{{ $height }} w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
        <div class="{{ $height }} rounded-full {{ \App\Support\Palette::dot($color) }} transition-all" style="width: {{ $value }}%"></div>
    </div>
    @if ($label)
        <span class="w-9 shrink-0 text-right text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400">{{ $value }}%</span>
    @endif
</div>
