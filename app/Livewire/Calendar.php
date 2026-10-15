<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Calendar')]
class Calendar extends Component
{
    /** Y-m */
    #[Url]
    public string $month = '';

    /** '' = everything visible | standalone | project id */
    #[Url]
    public string $project = '';

    #[Url]
    public bool $mine = false;

    #[Url]
    public bool $done = false;

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
    }

    private function first(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month);
    }

    public function previous(): void
    {
        $this->month = $this->first()->subMonth()->format('Y-m');
    }

    public function next(): void
    {
        $this->month = $this->first()->addMonth()->format('Y-m');
    }

    public function today(): void
    {
        $this->month = today()->format('Y-m');
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        $user = auth()->user();
        $first = $this->first();
        $gridStart = $first->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $tasks = Task::query()->visibleTo($user)
            ->with(['project', 'assignee'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when(! $this->done, fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($this->mine, fn ($q) => $q->involving($user))
            ->when($this->project === 'standalone', fn ($q) => $q->whereNull('project_id'))
            ->when(ctype_digit($this->project), fn ($q) => $q->where('project_id', (int) $this->project))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $t) => $t->due_date->toDateString());

        $deadlines = $this->project === 'standalone' ? collect() : Project::query()->visibleTo($user)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when(ctype_digit($this->project), fn ($q) => $q->whereKey((int) $this->project))
            ->get()
            ->groupBy(fn (Project $p) => $p->due_date->toDateString());

        $weeks = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->addDay()) {
            $weeks[intdiv($gridStart->diffInDays($day), 7)][] = $day;
        }

        return view('livewire.calendar', [
            'first' => $first,
            'weeks' => $weeks,
            'tasks' => $tasks,
            'deadlines' => $deadlines,
        ]);
    }
}
