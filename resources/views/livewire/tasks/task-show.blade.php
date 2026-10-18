<div>
    @php
        $user = auth()->user();
        $canUpdate = $user->can('update', $task);
        $canEdit = $user->can('edit', $task);
        $hasChildren = $this->subtasks->isNotEmpty() || $task->hasChildren();
        $workingHere = $mySession && $mySession->task_id === $task->id;
    @endphp

    {{-- Breadcrumb --}}
    <nav class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
        @if ($task->project)
            <a href="{{ route('projects.show', $task->project) }}" class="inline-flex items-center gap-1.5 font-medium hover:text-indigo-600">
                <span class="h-2 w-2 rounded-full {{ \App\Support\Palette::dot($task->project->color) }}"></span>{{ $task->project->name }}
            </a>
        @else
            <a href="{{ route('tasks.index', ['project' => 'standalone']) }}" class="font-medium hover:text-indigo-600">Standalone tasks</a>
        @endif
        @foreach ($this->ancestors as $ancestor)
            <x-icon name="chevron-right" class="h-3 w-3" />
            <a href="{{ route('tasks.show', $ancestor) }}" class="max-w-48 truncate hover:text-indigo-600">{{ $ancestor->title }}</a>
        @endforeach
    </nav>

    <x-page-header :title="($this->number ? 'Task '.$this->number.': ' : '').$task->title">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-status :value="$task->status" />
                <x-status :value="$task->priority" :dot="false" />
                @if ($task->category)
                    <x-status :value="$task->category" :dot="false" />
                @endif
                @if ($task->isOverdue())
                    <x-badge color="red"><x-icon name="alert" class="h-3 w-3" /> {{ $task->dueLabel() }}</x-badge>
                @elseif ($task->dueLabel())
                    <x-badge color="slate"><x-icon name="clock" class="h-3 w-3" /> {{ $task->dueLabel() }}</x-badge>
                @endif
                @if ($hasChildren)
                    <x-badge color="indigo">Progress follows subtasks</x-badge>
                @endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <button type="button" class="btn btn-secondary" wire:click="toggleFocus" title="{{ $pinned ? 'Unpin from Focus of the Day' : 'Pin to Focus of the Day' }}">
                <x-icon name="star" class="h-4 w-4 {{ $pinned ? 'fill-slate-500 text-slate-600' : '' }}" /> {{ $pinned ? 'Pinned' : 'Pin' }}
            </button>
            @if ($canUpdate && $task->isOpen())
                @if ($workingHere)
                    <button type="button" class="btn btn-success" wire:click="stopWorking"><x-icon name="stop" class="h-4 w-4" /> Stop working</button>
                @else
                    <button type="button" class="btn btn-secondary" wire:click="startWorking"><x-icon name="play" class="h-4 w-4" /> Start working</button>
                @endif
            @endif
            @if ($canEdit)
                <a href="{{ route('tasks.edit', $task) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
            @endif
            @can('delete', $task)
                <button type="button" class="btn btn-ghost text-rose-600 dark:text-rose-400" wire:click="deleteTask"
                        wire:confirm="{{ $hasChildren ? 'Delete this task and all of its subtasks? Their history is kept.' : 'Delete this task? Its history is kept.' }}">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($task->description)
                <section class="card card-body">
                    <div class="prose-text">{{ \App\Support\Text::render($task->description) }}</div>
                </section>
            @endif

            {{-- Quick update --}}
            @if ($canUpdate)
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Post an update</h2>
                        <span class="text-xs text-slate-400">0% Pending · 1–99% In Progress · 100% Completed</span>
                    </div>
                    <form wire:submit="saveUpdate" class="card-body space-y-4"
                          x-data="{ status: @entangle('qStatus'), progress: @entangle('qProgress') }">
                        @unless ($hasChildren)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="q-status">Status</label>
                                    <select id="q-status" class="input" x-model="status"
                                            @change="if (status === 'pending') progress = 0; if (status === 'completed') progress = 100; if (status === 'in_progress' && (progress < 1 || progress > 99)) progress = Math.min(Math.max(progress, 10), 99)">
                                        @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="q-progress">Progress <span class="font-semibold text-indigo-600 dark:text-indigo-400" x-text="progress + '%'"></span></label>
                                    <input id="q-progress" type="range" min="0" max="100" step="5" class="mt-2 w-full accent-indigo-600" x-model.number="progress"
                                           @input="if (['pending','in_progress','completed'].includes(status)) status = progress <= 0 ? 'pending' : (progress >= 100 ? 'completed' : 'in_progress')">
                                </div>
                            </div>
                        @else
                            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">This task's status and progress come from its subtasks. You can still post a remark or proof of work.</p>
                        @endunless

                        <div x-data="mentions" class="relative">
                            <label class="label" for="q-remark">Remark <span class="font-normal text-slate-400">— what changed? @mention people to notify them</span></label>
                            <textarea id="q-remark" rows="3" class="input" wire:model="qRemark" x-ref="input" @input="onInput" @keydown="onKeydown" placeholder="e.g. Finished the API, waiting on QA. @jamie.reyes can you review?"></textarea>
                            @include('partials.mention-menu')
                            @error('qRemark') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Proof of work <span class="font-normal text-slate-400">(screenshots, documents — up to 5, 10 MB each)</span></label>
                            <input type="file" multiple wire:model="proof" accept="{{ \App\Services\AttachmentService::accept() }}"
                                   class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-300">
                            <div wire:loading wire:target="proof" class="hint">Uploading…</div>
                            @error('proof') <p class="error">{{ $message }}</p> @enderror
                            @error('proof.*') <p class="error">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveUpdate,proof">Save update</button>
                        </div>
                    </form>
                </section>
            @endif

            {{-- Subtasks --}}
            @if ($this->subtasks->isNotEmpty() || $user->can('addSubtask', $task))
                <section class="card" id="subtasks">
                    <div class="card-header">
                        <h2 class="card-title">Subtasks <span class="ml-1 text-xs font-normal text-slate-400">{{ $this->subtasks->where('status', \App\Enums\TaskStatus::Completed)->count() }}/{{ $this->subtasks->count() }} done</span></h2>
                        @can('addSubtask', $task)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openSubtask"><x-icon name="plus" class="h-4 w-4" /> Add subtask</button>
                        @endcan
                    </div>
                    @if ($addingSubtask)
                        <form wire:submit="addSubtask" class="flex flex-wrap items-start gap-2 border-b border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                            <div class="min-w-48 flex-1">
                                <input type="text" class="input" wire:model="subTitle" placeholder="Subtask title" autofocus>
                                @error('subTitle') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            <select class="input w-auto" wire:model="subAssignee" aria-label="Assignee">
                                <option value="">Unassigned</option>
                                @foreach ($this->people as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" class="input w-auto" wire:model="subDue" aria-label="Due date">
                            <button type="submit" class="btn btn-primary">Add</button>
                            <button type="button" class="btn btn-ghost" wire:click="$set('addingSubtask', false)">Done</button>
                        </form>
                    @endif
                    <div class="divider">
                        @forelse ($this->subtasks as $sub)
                            <x-task-row :task="$sub" :show-project="false" :number="$sub->number" :indent="$sub->tree_depth + 1" wire:key="sub-{{ $sub->id }}" />
                        @empty
                            <p class="px-5 py-4 text-sm text-slate-500">No subtasks yet. Break the work into steps — this task's progress will follow them.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            {{-- Activity tabs --}}
            <section class="card">
                <div class="flex gap-5 border-b border-slate-100 px-5 dark:border-slate-800">
                    <button type="button" class="tab {{ $tab === 'remarks' ? 'active' : '' }}" wire:click="$set('tab', 'remarks')">Remarks <span class="text-xs text-slate-400">{{ $this->remarks->count() }}</span></button>
                    <button type="button" class="tab {{ $tab === 'comments' ? 'active' : '' }}" wire:click="$set('tab', 'comments')">Comments <span class="text-xs text-slate-400">{{ $this->comments->count() }}</span></button>
                    <button type="button" class="tab {{ $tab === 'history' ? 'active' : '' }}" wire:click="$set('tab', 'history')">History</button>
                </div>

                @if ($tab === 'remarks')
                    <div class="divider">
                        @forelse ($this->remarks as $remark)
                            <div class="flex gap-3 px-5 py-4" wire:key="remark-{{ $remark->id }}">
                                <x-avatar :user="$remark->user" size="h-8 w-8 text-xs" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $remark->user->name }}</span>
                                        <span class="text-xs text-slate-400" title="{{ $remark->created_at->format('M j, Y g:i A') }}">{{ $remark->created_at->diffForHumans() }}</span>
                                        @if ($remark->isReviewed())
                                            <x-badge color="emerald" title="{{ $remark->reviewed_at->format('M j, Y g:i A') }}"><x-icon name="check" class="h-3 w-3" /> Reviewed by {{ $remark->reviewer?->name }}</x-badge>
                                        @endif
                                    </div>
                                    <div class="prose-text mt-1">{{ \App\Support\Text::render($remark->body) }}</div>
                                    @if ($remark->attachments->isNotEmpty())
                                        <p class="mt-3 mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Proof of work</p>
                                        <x-attachment-list :attachments="$remark->attachments" :deletable="true" />
                                    @endif
                                </div>
                                @can('review', $remark)
                                    <button type="button" class="btn btn-secondary btn-sm h-fit" wire:click="reviewRemark({{ $remark->id }})"><x-icon name="eye" class="h-3.5 w-3.5" /> Mark reviewed</button>
                                @endcan
                            </div>
                        @empty
                            <x-empty icon="chat" title="No remarks yet" description="Post an update with a remark to report progress. Supervisors can mark remarks as reviewed." />
                        @endforelse
                    </div>
                @elseif ($tab === 'comments')
                    <div class="divider">
                        @foreach ($this->comments as $comment)
                            <div class="group flex gap-3 px-5 py-4" wire:key="comment-{{ $comment->id }}">
                                <x-avatar :user="$comment->user" size="h-8 w-8 text-xs" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $comment->user->name }}</span>
                                        <span class="text-xs text-slate-400">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="prose-text mt-1">{{ \App\Support\Text::render($comment->body) }}</div>
                                    <x-attachment-list :attachments="$comment->attachments" :deletable="true" class="mt-2" />
                                </div>
                                @can('delete', $comment)
                                    <button type="button" class="icon-btn h-fit opacity-0 group-hover:opacity-100 hover:text-rose-600" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                    @can('comment', $task)
                        <form wire:submit="addComment" class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800">
                            <div x-data="mentions" class="relative">
                                <textarea rows="3" class="input" wire:model="commentBody" x-ref="input" @input="onInput" @keydown="onKeydown" placeholder="Write a comment… use @username to mention someone"></textarea>
                                @include('partials.mention-menu')
                                @error('commentBody') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <input type="file" multiple wire:model="commentFiles" accept="{{ \App\Services\AttachmentService::accept() }}"
                                       class="text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2.5 file:py-1.5 file:text-xs file:font-medium dark:file:bg-slate-800 dark:file:text-slate-300">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addComment,commentFiles">Comment</button>
                            </div>
                            @error('commentFiles.*') <p class="error">{{ $message }}</p> @enderror
                        </form>
                    @endcan
                @else
                    <ol class="relative space-y-5 px-5 py-5">
                        @forelse ($this->history as $entry)
                            @include('partials.history-entry', ['entry' => $entry])
                        @empty
                            <li class="text-sm text-slate-500">No history yet.</li>
                        @endforelse
                    </ol>
                @endif
            </section>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <section class="card">
                <div class="card-body space-y-4">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Progress</p>
                        <x-progress :value="$task->progress" :label="true" height="h-2" class="mt-1.5" />
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div class="col-span-2">
                            <dt class="text-xs text-slate-500">Assigned to</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <x-avatar :user="$task->assignee" />
                                <span class="font-medium">{{ $task->assignee?->name ?? 'Unassigned' }}</span>
                            </dd>
                        </div>
                        <div><dt class="text-xs text-slate-500">Department</dt><dd class="mt-0.5">{{ $task->department?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Created by</dt><dd class="mt-0.5">{{ $task->creator?->name }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Start</dt><dd class="mt-0.5">{{ $task->start_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Due</dt><dd class="mt-0.5 {{ $task->isOverdue() ? 'font-semibold' : '' }}">{{ $task->due_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Created</dt><dd class="mt-0.5">{{ $task->created_at->format('M j, Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Completed</dt><dd class="mt-0.5">{{ $task->completed_at?->format('M j, Y') ?? '—' }}</dd></div>
                        @if ($task->latest_update_at)
                            <div class="col-span-2"><dt class="text-xs text-slate-500">Last update</dt><dd class="mt-0.5">{{ $task->latest_update_at->diffForHumans() }}{{ $task->latestUpdater ? ' by '.$task->latestUpdater->name : '' }}</dd></div>
                        @endif
                    </dl>
                </div>
            </section>

            @if ($this->workingNow->isNotEmpty())
                <section class="card card-body">
                    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300"><x-icon name="bolt" class="h-3.5 w-3.5" /> Working on this now</p>
                    @foreach ($this->workingNow as $session)
                        <div class="flex items-center gap-2 py-1 text-sm">
                            <x-avatar :user="$session->user" size="h-6 w-6 text-[10px]" /> {{ $session->user->name }}
                            <span class="ml-auto text-xs text-slate-400">since {{ $session->started_at->format('g:i A') }}</span>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- Collaborators --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Collaborators</h2></div>
                <div class="card-body space-y-2">
                    @forelse ($task->collaborators as $collaborator)
                        <div class="flex items-center gap-2 text-sm" wire:key="collab-{{ $collaborator->id }}">
                            <x-avatar :user="$collaborator" size="h-6 w-6 text-[10px]" />
                            <span class="flex-1">{{ $collaborator->name }} <span class="text-xs text-slate-400">{{ $collaborator->department?->code }}</span></span>
                            @can('manageCollaborators', $task)
                                <button type="button" class="icon-btn" wire:click="removeCollaborator({{ $collaborator->id }})" title="Remove"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No collaborators.</p>
                    @endforelse
                    @can('manageCollaborators', $task)
                        <form wire:submit="addCollaborator" class="flex gap-2 pt-2">
                            <select class="input input-sm flex-1" wire:model="newCollaborator" aria-label="Add collaborator">
                                <option value="">Add a person…</option>
                                @foreach ($this->people as $person)
                                    @continue($person->id === $task->assignee_id || $task->collaborators->contains('id', $person->id))
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Add</button>
                        </form>
                        @error('newCollaborator') <p class="error">{{ $message }}</p> @enderror
                    @endcan
                </div>
            </section>

            {{-- Files --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Files</h2><span class="text-xs text-slate-400">{{ $task->attachments->count() }}</span></div>
                <div class="card-body space-y-3">
                    <x-attachment-list :attachments="$task->attachments" :deletable="true" />
                    @if ($task->attachments->isEmpty())
                        <p class="text-sm text-slate-500">No files attached.</p>
                    @endif
                    @if ($canUpdate)
                        <form wire:submit="uploadFiles" class="space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <input type="file" multiple wire:model="files" accept="{{ \App\Services\AttachmentService::accept() }}"
                                   class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2.5 file:py-1.5 file:text-xs file:font-medium dark:file:bg-slate-800 dark:file:text-slate-300">
                            @error('files') <p class="error">{{ $message }}</p> @enderror
                            @error('files.*') <p class="error">{{ $message }}</p> @enderror
                            <button type="submit" class="btn btn-secondary btn-sm w-full" wire:loading.attr="disabled" wire:target="uploadFiles,files"><x-icon name="upload" class="h-4 w-4" /> Upload</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
