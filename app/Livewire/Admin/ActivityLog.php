<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Title('Activity logs')]
class ActivityLog extends Component
{
    use WithPagination;

    #[Url]
    public string $group = '';

    #[Url]
    public string $user = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function people(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        $this->authorize('full-access');

        $logs = Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->group, fn ($q) => $q->where('event', 'like', $this->group.'.%'))
            ->when($this->user, fn ($q) => $q->where('causer_type', 'user')->where('causer_id', $this->user))
            ->when(trim($this->q) !== '', fn ($q) => $q->where('description', 'like', '%'.trim($this->q).'%'))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->paginate(30);

        return view('livewire.admin.activity-log', ['logs' => $logs]);
    }
}
