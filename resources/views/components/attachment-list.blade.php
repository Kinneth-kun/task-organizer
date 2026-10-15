{{-- Files: images as a thumbnail gallery, everything else as a list. --}}
@props(['attachments', 'deletable' => false])
@php
    $images = $attachments->filter->isImage();
    $files = $attachments->reject->isImage();
@endphp
@if ($attachments->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'space-y-2']) }}>
        @if ($images->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($images as $file)
                    <a href="{{ $file->url() }}" target="_blank" class="group relative block h-20 w-20 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700" title="{{ $file->original_name }}">
                        <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                    </a>
                @endforeach
            </div>
        @endif
        @foreach ($files as $file)
            <div class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                <x-icon name="document" class="h-4 w-4 text-slate-400" />
                <a href="{{ $file->url() }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-700 hover:text-indigo-600 dark:text-slate-200">{{ $file->original_name }}</a>
                <span class="text-xs text-slate-400">{{ $file->humanSize() }}</span>
                <a href="{{ $file->url(true) }}" class="icon-btn" title="Download"><x-icon name="download" class="h-4 w-4" /></a>
                @if ($deletable && auth()->user()->can('delete', $file))
                    <button type="button" class="icon-btn hover:text-rose-600" wire:click="deleteAttachment({{ $file->id }})" wire:confirm="Delete {{ $file->original_name }}?" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                @endif
            </div>
        @endforeach
    </div>
@endif
