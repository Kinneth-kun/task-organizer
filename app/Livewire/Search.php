<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Search across projects, tasks and comments — only what the person can see.
 */
#[Title('Search')]
class Search extends Component
{
    #[Url]
    public string $q = '';

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->q);
        $results = ['projects' => collect(), 'tasks' => collect(), 'comments' => collect()];

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $results['projects'] = Project::query()->visibleTo($user)->with('owner')
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like))
                ->orderBy('name')->limit(10)->get();

            $results['tasks'] = Task::query()->visibleTo($user)->with(['project', 'assignee', 'parent'])
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('latest_remark', 'like', $like))
                ->orderByRaw('case when status in (?, ?, ?) then 0 else 1 end', ['pending', 'in_progress', 'delayed'])
                ->latest('updated_at')->limit(25)->get();

            $results['comments'] = TaskComment::query()->with(['user', 'task.project'])
                ->whereIn('task_id', Task::query()->visibleTo($user)->select('tasks.id'))
                ->where('body', 'like', $like)
                ->latest()->limit(15)->get();
        }

        return view('livewire.search', ['term' => $term, 'results' => $results]);
    }
}
