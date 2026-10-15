<div>
    <x-page-header title="Notifications" description="Assignments, mentions, updates, deadlines and your daily briefing.">
        <x-slot:actions>
            <button type="button" class="btn btn-secondary" wire:click="markAllRead"><x-icon name="check" class="h-4 w-4" /> Mark all read</button>
            <button type="button" class="btn btn-ghost" wire:click="clearRead" wire:confirm="Delete all read notifications?"><x-icon name="trash" class="h-4 w-4" /> Clear read</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4 flex flex-wrap items-center gap-3 p-3">
        <select class="input w-auto" wire:model.live="type" aria-label="Type">
            <option value="">All types</option>
            @foreach (\App\Enums\NotificationType::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="unread"> Unread only</label>
        <a href="{{ route('profile') }}#email" class="ml-auto text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Email preferences →</a>
    </div>

    <div class="card overflow-hidden">
        <div class="divider">
            @forelse ($notifications as $n)
                @php($type = \App\Enums\NotificationType::tryFrom($n->type))
                <div class="flex gap-4 px-5 py-4 {{ $n->read_at ? '' : 'bg-indigo-50/40 dark:bg-indigo-500/5' }}" wire:key="n-{{ $n->id }}">
                    <span class="h-fit rounded-lg p-2 {{ \App\Support\Palette::soft($type?->color()) }}"><x-icon :name="$type?->icon() ?? 'bell'" class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('notifications.open', $n->id) }}" class="text-sm text-slate-800 hover:text-indigo-600 dark:text-slate-100 {{ $n->read_at ? '' : 'font-semibold' }}">{{ $n->data['title'] ?? 'Notification' }}</a>
                        @if (! empty($n->data['message']))
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $n->data['message'] }}</p>
                        @endif
                        @if (! empty($n->data['lines']))
                            <ul class="mt-2 space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @foreach ($n->data['lines'] as $line)
                                    <li>• {{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="mt-1 text-xs text-slate-400">{{ $type?->label() }} · {{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @unless ($n->read_at)
                        <button type="button" class="icon-btn h-fit" wire:click="markRead('{{ $n->id }}')" title="Mark read"><x-icon name="check" class="h-4 w-4" /></button>
                    @endunless
                </div>
            @empty
                <x-empty icon="bell" title="No notifications" description="You'll hear about assignments, mentions, deadlines and updates on your tasks here." />
            @endforelse
        </div>
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
