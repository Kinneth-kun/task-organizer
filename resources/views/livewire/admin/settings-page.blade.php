<div>
    <x-page-header title="Settings" description="System-wide rules for deadlines, project health, the daily briefing and sign-in security." />

    <form wire:submit="save" class="space-y-6">
        @foreach ($groups as $group => $fields)
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ $group }}</h2></div>
                <div class="divider">
                    @foreach ($fields as $field)
                        @php($model = 'values.'.str_replace('.', '__', $field['key']))
                        <div class="grid gap-3 px-5 py-4 sm:grid-cols-3 sm:items-center">
                            <div class="sm:col-span-2">
                                <label class="text-sm font-medium text-slate-800 dark:text-slate-100" for="{{ $field['key'] }}">{{ $field['label'] }}</label>
                                <p class="text-xs text-slate-500">{{ $field['help'] }}</p>
                            </div>
                            <div>
                                @if ($field['type'] === 'bool')
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
                                        <input id="{{ $field['key'] }}" type="checkbox" class="checkbox" wire:model="{{ $model }}"> Enabled
                                    </label>
                                @elseif ($field['type'] === 'int')
                                    <input id="{{ $field['key'] }}" type="number" class="input" wire:model="{{ $model }}" min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? '' }}">
                                @else
                                    <input id="{{ $field['key'] }}" type="text" class="input" wire:model="{{ $model }}">
                                @endif
                                @error($model) <p class="error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save settings</button>
        </div>
    </form>
</div>
