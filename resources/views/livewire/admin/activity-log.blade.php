<div>
    <x-page-header title="Audit trail" description="Who did what, and when — sign-ins, changes to tasks and projects, and administration." />

    <div class="card mb-4 flex flex-wrap items-center gap-3 p-3">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" class="input pl-9" placeholder="Search descriptions…" wire:model.live.debounce.300ms="q" aria-label="Search">
        </div>
        <select class="input w-auto" wire:model.live="group" aria-label="Area">
            <option value="">All areas</option>
            @foreach (\App\Support\Audit::GROUPS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="input w-auto" wire:model.live="user" aria-label="Person">
            <option value="">Anyone</option>
            @foreach ($this->people as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <input type="date" class="input w-auto" wire:model.live="from" aria-label="From">
        <input type="date" class="input w-auto" wire:model.live="to" aria-label="To">
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>When</th><th>Who</th><th>What</th><th>Event</th><th>IP address</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $subject = $log->subject;
                        $link = match (true) {
                            $subject instanceof \App\Models\Task => route('tasks.show', $subject),
                            $subject instanceof \App\Models\Project => route('projects.show', $subject),
                            default => null,
                        };
                    @endphp
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="text-xs whitespace-nowrap text-slate-500" title="{{ $log->created_at->format('M j, Y g:i:s A') }}">{{ $log->created_at->format('M j, g:i A') }}</td>
                        <td>
                            @if ($log->causer)
                                <span class="flex items-center gap-2"><x-avatar :user="$log->causer" size="h-6 w-6 text-[10px]" /> <span class="text-sm">{{ $log->causer->name }}</span></span>
                            @else
                                <span class="text-xs text-slate-400">System / guest</span>
                            @endif
                        </td>
                        <td class="text-sm">
                            @if ($link)
                                <a href="{{ $link }}" class="hover:text-indigo-600">{{ $log->description }}</a>
                            @else
                                {{ $log->description }}
                            @endif
                        </td>
                        <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $log->event }}</code></td>
                        <td class="font-mono text-xs text-slate-500">{{ $log->properties['ip'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="history" title="No activity matches" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
