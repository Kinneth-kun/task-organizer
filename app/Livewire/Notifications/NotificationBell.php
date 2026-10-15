<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    #[On('notifications-changed')]
    public function refreshBell(): void
    {
        // Re-render.
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notifications.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
