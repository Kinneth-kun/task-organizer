<div>
    @php
        $back = $task ? route('tasks.show', $task) : ($this->parent ? route('tasks.show', $this->parent) : ($this->selectedProject ? route('projects.show', $this->selectedProject) : route('tasks.index')));
        $postLaunch = ! $this->parent && $this->selectedProject?->isCompleted();
    @endphp

    <x-page-header :title="$task ? 'Edit task' : ($this->parent ? 'New subtask' : 'New task')" :back="$back"
                   :description="$this->parent ? 'Under “'.$this->parent->title.'”'.($this->parent->project ? ' in '.$this->parent->project->name : '') : null" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="card space-y-5 p-6 lg:col-span-2">
            <div>
                <label class="label" for="title">Title</label>
                <input id="title" type="text" class="input" wire:model="title" placeholder="What needs to be done?" autofocus>
                @error('title') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="description">Description <span class="font-normal text-slate-400">(optional)</span></label>
                <textarea id="description" rows="5" class="input" wire:model="description" placeholder="Context, acceptance criteria, links…"></textarea>
                @error('description') <p class="error">{{ $message }}</p> @enderror
            </div>

            @if (! $task && ! $this->parent)
                <div>
                    <label class="label" for="project">Where does it belong?</label>
                    <select id="project" class="input" wire:model.live="projectId">
                        <option value="">Standalone task — one-off work, no project</option>
                        @foreach ($this->projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}{{ $p->isCompleted() ? ' (completed — post-launch work)' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="hint">{{ $projectId === '' ? 'Standalone tasks have the same statuses, history and reminders, without a project roll-up.' : "Counts toward the project's progress and health." }}</p>
                    @error('projectId') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($postLaunch || ($task && $task->category && ! $task->parent_id))
                <div>
                    <label class="label" for="category">Type of post-launch work</label>
                    <select id="category" class="input" wire:model="category">
                        <option value="">Enhancement (default)</option>
                        @foreach (\App\Enums\TaskCategory::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="hint">This project is completed, so new work is logged as post-launch and doesn't change its progress.</p>
                </div>
            @endif

            <div>
                <span class="label">Collaborators <span class="font-normal text-slate-400">(optional)</span></span>
                <div x-data="{ q: '' }" class="rounded-lg border border-slate-200 dark:border-slate-700">
                    <input type="search" x-model="q" class="w-full rounded-t-lg border-0 border-b border-slate-200 bg-transparent px-3 py-2 text-sm focus:ring-0 dark:border-slate-700" placeholder="Find people…">
                    <div class="max-h-48 overflow-y-auto p-1">
                        @foreach ($this->people as $person)
                            @continue((string) $person->id === $assigneeId)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
                                   x-show="!q || @js(strtolower($person->name.' '.$person->username)).includes(q.toLowerCase())" wire:key="collab-{{ $person->id }}">
                                <input type="checkbox" class="checkbox" @checked(in_array($person->id, $collaboratorIds, true)) wire:click="toggleCollaborator({{ $person->id }})">
                                <x-avatar :user="$person" size="h-6 w-6 text-[10px]" />
                                <span class="text-sm">{{ $person->name }}</span>
                                <span class="ml-auto text-xs text-slate-400">{{ $person->department?->code }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <p class="hint">Collaborators can see and update the task. They are notified when added.</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <div>
                    <label class="label" for="assignee">Assigned to</label>
                    <select id="assignee" class="input" wire:model.live="assigneeId">
                        <option value="">Unassigned</option>
                        @foreach ($this->people->groupBy(fn ($p) => $p->department?->name ?? 'No department') as $dept => $group)
                            <optgroup label="{{ $dept }}">
                                @foreach ($group as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}{{ $person->id === auth()->id() ? ' (me)' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('assigneeId') <p class="error">{{ $message }}</p> @enderror
                    @if (auth()->user()->isIntern())
                        <p class="hint">Interns can only assign tasks to themselves.</p>
                    @endif
                </div>

                <div>
                    <label class="label" for="priority">Priority</label>
                    <select id="priority" class="input" wire:model="priority">
                        @foreach (\App\Enums\Priority::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="start">Start @if ($parentId)<span class="text-rose-500">*</span>@endif</label>
                        <input id="start" type="date" class="input" wire:model="startDate">
                    </div>
                    <div>
                        <label class="label" for="due">{{ $parentId ? 'End' : 'Due' }} @if ($parentId)<span class="text-rose-500">*</span>@endif</label>
                        <input id="due" type="date" class="input" wire:model="dueDate">
                    </div>
                </div>
                @error('startDate') <p class="error -mt-3">{{ $message }}</p> @enderror
                @error('dueDate') <p class="error -mt-3">{{ $message }}</p> @enderror
                <div class="-mt-2 flex flex-wrap gap-1.5">
                    @foreach (['Today' => 0, 'Tomorrow' => 1, '3 days' => 3, '1 week' => 7] as $label => $days)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="setDue({{ $days }})">{{ $label }}</button>
                    @endforeach
                </div>

                @unless ($task)
                    <div x-data="{ progress: @entangle('progress'), status: @entangle('status') }">
                        <label class="label" for="status">Starting status</label>
                        <select id="status" class="input" x-model="status"
                                @change="if (status === 'pending') progress = 0; if (status === 'completed') progress = 100; if (status === 'in_progress' && (progress < 1 || progress > 99)) progress = 10">
                            @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="label mt-4" for="progress">Progress <span class="font-normal text-slate-400" x-text="progress + '%'"></span></label>
                        <input id="progress" type="range" min="0" max="100" step="5" class="w-full accent-indigo-600" x-model.number="progress"
                               @input="status = progress <= 0 ? 'pending' : (progress >= 100 ? 'completed' : (['pending','completed'].includes(status) ? 'in_progress' : status))">
                    </div>
                @endunless
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ $back }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $task ? 'Save changes' : 'Create task' }}</button>
            </div>
        </div>
    </form>
</div>
