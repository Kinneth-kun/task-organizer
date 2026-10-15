<div>
    <button type="button" class="btn btn-primary btn-sm" wire:click="openModal">
        <x-icon name="plus" class="h-4 w-4" /><span class="hidden sm:inline">New task</span>
    </button>

    @if ($open)
        <x-modal title="Quick task" close="$set('open', false)">
            <form wire:submit="save" class="space-y-4" id="quick-create-form">
                <div>
                    <label class="label" for="qc-title">What needs doing?</label>
                    <input id="qc-title" type="text" class="input" wire:model="title" placeholder="e.g. Send the revised floor plan to Leasing" autofocus>
                    @error('title') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="qc-project">Project</label>
                        <select id="qc-project" class="input" wire:model="projectId">
                            <option value="">Standalone task</option>
                            @foreach ($this->projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="qc-priority">Priority</label>
                        <select id="qc-priority" class="input" wire:model="priority">
                            @foreach (\App\Enums\Priority::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="qc-due">Due date</label>
                    <input id="qc-due" type="date" class="input" wire:model="due">
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (['Today' => 0, 'Tomorrow' => 1, 'In 3 days' => 3, 'In a week' => 7] as $label => $days)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setDue({{ $days }})">{{ $label }}</button>
                        @endforeach
                    </div>
                    @error('due') <p class="error">{{ $message }}</p> @enderror
                </div>
                <p class="hint">The task is assigned to you. Open it afterwards to add details, subtasks or collaborators.</p>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="$set('open', false)">Cancel</button>
                <button type="submit" form="quick-create-form" class="btn btn-primary" wire:loading.attr="disabled">Create task</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
