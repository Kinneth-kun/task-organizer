{{-- Any enum with label() and color(): task status, priority, project status, health, role, category. --}}
@props(['value', 'dot' => true])
@if ($value)
    <x-badge :color="$value->color()" :dot="$dot" {{ $attributes }}>{{ $value->label() }}</x-badge>
@endif
