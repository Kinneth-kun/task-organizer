<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Activity Logs')]
class NotificationCenter extends Component
{
    use Toasts, WithPagination;

    #[Url]
    public bool $unread = false;

    #[Url]
    public string $type = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->update(['read_at' => now()]);
        $this->dispatch('notifications-changed');
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('notifications-changed');
        $this->toast('All caught up.', 'info');
    }

    public function clearRead(): void
    {
        auth()->user()->readNotifications()->delete();
        $this->toast('Read entries cleared.', 'info');
    }

    public function render(): View
    {
        $notifications = auth()->user()->notifications()
            ->when($this->unread, fn ($q) => $q->whereNull('read_at'))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->latest()
            ->paginate(20);

        return view('livewire.notifications.notification-center', ['notifications' => $notifications]);
    }
}
