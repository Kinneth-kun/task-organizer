<div class="relative" x-data="{ open: false }" @click.outside="open = false" wire:poll.30s>
    <button type="button" class="icon-btn relative" @click="open = !open" aria-label="Notifications">
        <x-icon name="bell" class="h-6 w-6" />
        @if ($unread)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition class="menu right-0 w-[22rem] max-w-[calc(100vw-2rem)] py-0">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Notifications</p>
            @if ($unread)
                <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" wire:click="markAllRead">Mark all read</button>
            @endif
        </div>
        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
            @forelse ($recent as $n)
                @php($type = \App\Enums\NotificationType::tryFrom($n->type))
                <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $n->read_at ? '' : 'bg-indigo-50/50 dark:bg-indigo-500/5' }}">
                    <span class="mt-0.5 rounded-lg p-1.5 {{ \App\Support\Palette::soft($type?->color()) }}"><x-icon :name="$type?->icon() ?? 'bell'" class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm leading-snug text-slate-800 dark:text-slate-100 {{ $n->read_at ? '' : 'font-medium' }}">{{ $n->data['title'] ?? 'Notification' }}</p>
                        @if (! empty($n->data['message']))
                            <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $n->data['message'] }}</p>
                        @endif
                        <p class="mt-1 text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @unless ($n->read_at)
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                    @endunless
                </a>
            @empty
                <x-empty icon="bell" title="You're all caught up" class="py-8" />
            @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-indigo-600 hover:bg-slate-50 dark:border-slate-800 dark:text-indigo-400 dark:hover:bg-slate-800/50">View all in Activity Logs</a>
    </div>
</div>
