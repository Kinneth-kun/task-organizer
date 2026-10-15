{{-- Rendered only while open. `close` is the Livewire expression that closes it. --}}
@props(['title', 'close', 'width' => 'max-w-lg'])
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 pt-[10vh] backdrop-blur-sm"
     x-data x-on:keydown.escape.window="$wire.{{ $close }}" wire:click.self="{{ $close }}" role="dialog" aria-modal="true">
    <div class="card w-full {{ $width }} shadow-xl">
        <div class="card-header">
            <h2 class="card-title text-base">{{ $title }}</h2>
            <button type="button" class="icon-btn" wire:click="{{ $close }}" aria-label="Close"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
        <div class="card-body">{{ $slot }}</div>
        @if (isset($footer))
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">{{ $footer }}</div>
        @endif
    </div>
</div>
