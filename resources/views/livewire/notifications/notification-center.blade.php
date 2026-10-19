<div>
    <x-page-header title="Activity Logs" description="Assignments, mentions, updates, deadlines and your daily briefing.">
        <x-slot:actions>
            <button type="button" class="btn btn-secondary" wire:click="markAllRead"><x-icon name="check" class="h-4 w-4" /> Mark all read</button>
            <button type="button" class="btn btn-ghost" wire:click="clearRead" wire:confirm="Delete all read entries? Unread entries are kept."><x-icon name="trash" class="h-4 w-4" /> Clear read</button>
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

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>When</th><th>Type</th><th>Activity</th><th>Details</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                @forelse ($notifications as $n)
                    @php($type = \App\Enums\NotificationType::tryFrom($n->type))
                    <tr wire:key="n-{{ $n->id }}" class="{{ $n->read_at ? '' : 'bg-indigo-50/40 dark:bg-indigo-500/5' }}">
                        <td class="text-xs whitespace-nowrap text-slate-500" title="{{ $n->created_at->format('M j, Y g:i:s A') }}">
                            {{ $n->created_at->format('M j, g:i A') }}
                            <span class="block text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><span class="rounded-lg p-1.5 {{ \App\Support\Palette::soft($type?->color()) }}"><x-icon :name="$type?->icon() ?? 'bell'" class="h-4 w-4" /></span> {{ $type?->label() ?? 'Notification' }}</span>
                        </td>
                        <td class="min-w-56 text-sm">
                            <a href="{{ route('notifications.open', $n->id) }}" class="text-slate-800 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400 {{ $n->read_at ? '' : 'font-semibold' }}">{{ $n->data['title'] ?? 'Notification' }}</a>
                        </td>
                        <td class="min-w-64 text-sm text-slate-500 dark:text-slate-400">
                            @if (! empty($n->data['message']))
                                <p>{{ $n->data['message'] }}</p>
                            @endif
                            @if (! empty($n->data['lines']))
                                <ul class="mt-1 space-y-0.5 text-xs">
                                    @foreach ($n->data['lines'] as $line)
                                        <li>• {{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (empty($n->data['message']) && empty($n->data['lines']))
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($n->read_at)
                                <x-badge>Read</x-badge>
                            @else
                                <x-badge color="indigo" :dot="true">Unread</x-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            @unless ($n->read_at)
                                <button type="button" class="icon-btn" wire:click="markRead('{{ $n->id }}')" title="Mark read"><x-icon name="check" class="h-4 w-4" /></button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="bell" title="No activity yet" description="You'll see assignments, mentions, deadlines and updates on your tasks here." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
