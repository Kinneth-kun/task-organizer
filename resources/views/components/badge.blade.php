@props(['color' => 'slate', 'dot' => false])
<span {{ $attributes->merge(['class' => 'badge '.\App\Support\Palette::badge($color)]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ \App\Support\Palette::dot($color) }}"></span>
    @endif
    {{ $slot }}
</span>
