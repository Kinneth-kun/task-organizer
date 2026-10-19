<?php

namespace App\Livewire;

use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Models\Remark;
use App\Services\CollaborationService;
use App\Support\DailyReport as Report;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One day's updates, grouped by project, for the daily meeting. Supervisors mark each update
 * reported once it has been covered; everyone else sees the updates on tasks they can see.
 */
#[Title('Daily report')]
class DailyReport extends Component
{
    use Toasts;

    /** Y-m-d; empty means today. */
    #[Url]
    public string $date = '';

    /** '' | pending | reported */
    #[Url]
    public string $state = '';

    /** Department filter for supervisors. */
    #[Url]
    public string $department = '';

    public function mount(): void
    {
        if (! auth()->user()->isSupervisor()) {
            $this->department = '';
        }
        if (! array_key_exists($this->state, Report::STATES)) {
            $this->state = '';
        }
    }

    /* ------------------------------------------------------------------ Navigation */

    public function previousDay(): void
    {
        $this->date = $this->day()->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $next = $this->day()->addDay();
        $this->date = $next->isToday() ? '' : Report::day($next->toDateString())->toDateString();
    }

    public function today(): void
    {
        $this->date = '';
    }

    /* ------------------------------------------------------------------ Actions */

    public function markReported(int $remarkId, CollaborationService $collaboration): void
    {
        $remark = Remark::with(['task', 'user'])->findOrFail($remarkId);
        $this->authorize('review', $remark);
        $collaboration->review($remark, auth()->user());
        $this->forget();
        $this->toast('Marked as reported.');
    }

    /** Marks every update shown (optionally one project's) that this person may report. */
    public function markAllReported(?string $project, CollaborationService $collaboration): void
    {
        $user = auth()->user();
        $remarks = Report::query($user, $this->day(), $this->departmentId(), 'pending')->get()
            ->when($project !== null, fn (Collection $all) => $all->filter(
                fn (Remark $remark) => ($remark->task?->project?->name ?? Report::STANDALONE) === $project
            ))
            ->filter(fn (Remark $remark) => $user->can('review', $remark));

        foreach ($remarks as $remark) {
            $collaboration->review($remark, $user);
        }

        $this->forget();
        $count = $remarks->count();
        $this->toast($count ? "Marked {$count} ".str('update')->plural($count).' as reported.' : 'Nothing left for you to report here.', $count ? 'success' : 'info');
    }

    /* ------------------------------------------------------------------ Data */

    public function day(): CarbonImmutable
    {
        return Report::day($this->date);
    }

    #[Computed]
    public function remarks(): Collection
    {
        return Report::query(auth()->user(), $this->day(), $this->departmentId(), $this->state)->get();
    }

    #[Computed]
    public function groups(): Collection
    {
        return Report::byProject($this->remarks);
    }

    #[Computed]
    public function changes(): Collection
    {
        return Report::changes($this->remarks);
    }

    /** Totals for the whole day, whatever the reported/not reported filter shows. */
    #[Computed]
    public function summary(): array
    {
        $all = $this->state === '' ? $this->remarks : Report::query(auth()->user(), $this->day(), $this->departmentId())->get();
        $user = auth()->user();

        return [
            'total' => $all->count(),
            'pending' => $all->whereNull('reviewed_at')->count(),
            'reported' => $all->whereNotNull('reviewed_at')->count(),
            'people' => $all->pluck('user_id')->unique()->count(),
            'projects' => $all->map(fn (Remark $r) => $r->task?->project_id ?? 0)->unique()->count(),
            'reportable' => $all->whereNull('reviewed_at')->filter(fn (Remark $r) => $user->can('review', $r))->count(),
        ];
    }

    /** Unreported updates from earlier days. */
    #[Computed]
    public function backlog(): array
    {
        return Report::pendingBefore(auth()->user(), $this->day(), $this->departmentId());
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->active()->orderBy('name')->get();
    }

    private function departmentId(): ?int
    {
        return $this->department !== '' ? (int) $this->department : null;
    }

    private function forget(): void
    {
        unset($this->remarks, $this->groups, $this->changes, $this->summary, $this->backlog);
    }

    public function render(): View
    {
        return view('livewire.daily-report');
    }
}
