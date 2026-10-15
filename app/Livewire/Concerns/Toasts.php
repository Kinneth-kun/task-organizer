<?php

namespace App\Livewire\Concerns;

trait Toasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /** For a toast that should survive a redirect. */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['message' => $message, 'type' => $type]);
    }
}
