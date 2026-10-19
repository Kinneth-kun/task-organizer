@php
    $system = $entry->isSystem();
    $meta = $entry->meta ?? [];
@endphp
<li class="flex gap-3">
    @if ($system || ! $entry->user)
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800"><x-icon name="{{ $entry->type->value === 'rollup' ? 'refresh' : 'clock' }}" class="h-3.5 w-3.5" /></span>
    @else
        <x-avatar :user="$entry->user" />
    @endif
    <div class="min-w-0 flex-1 text-sm">
        <p class="text-slate-600 dark:text-slate-300">
            <span class="font-medium text-slate-900 dark:text-white">{{ $entry->user?->name ?? 'System' }}</span>
            @switch($entry->type->value)
                @case('created')
                    created the task
                    @break
                @case('update')
                    @if ($entry->old_status !== $entry->new_status)
                        changed status <x-status :value="$entry->old_status" :dot="false" /> → <x-status :value="$entry->new_status" :dot="false" />
                    @endif
                    @if ($entry->old_progress !== $entry->new_progress)
                        progress {{ $entry->old_progress }}% → {{ $entry->new_progress }}%
                    @endif
                    @break
                @case('remark')
                    added a remark
                    @break
                @case('assignment')
                    {{ ($meta['new_assignee'] ?? null) ? 'assigned it to '.$meta['new_assignee'] : 'unassigned it' }}{{ ($meta['old_assignee'] ?? null) ? ' (was '.$meta['old_assignee'].')' : '' }}
                    @break
                @case('details')
                    edited {{ implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), array_keys($meta['changes'] ?? []))) }}
                    @break
                @case('auto_delayed')
                    marked it <x-status :value="$entry->new_status" :dot="false" /> — it was due {{ isset($meta['due_date']) ? \Illuminate\Support\Carbon::parse($meta['due_date'])->format('M j, Y') : '' }}
                    @break
                @case('undelayed')
                    cleared the delay after the due date moved — now <x-status :value="$entry->new_status" :dot="false" />
                    @break
                @case('rollup')
                    updated it from its subtasks: {{ $entry->old_progress }}% → {{ $entry->new_progress }}%
                    @if ($entry->old_status !== $entry->new_status)
                        · <x-status :value="$entry->new_status" :dot="false" />
                    @endif
                    @break
            @endswitch
            <span class="text-xs text-slate-400" title="{{ $entry->created_at->format('M j, Y g:i A') }}">· {{ $entry->created_at->diffForHumans() }}</span>
        </p>
        @if ($entry->remark)
            <div class="prose-text mt-1 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">{{ \App\Support\Text::render($entry->remark) }}</div>
        @endif
        @if (! empty($meta['requested_status']))
            {{-- Older entries, from when overdue tasks were marked Delayed automatically. --}}
            <p class="mt-1 text-xs text-slate-400">Kept as Delayed because the task was past its due date.</p>
        @endif
    </div>
</li>
