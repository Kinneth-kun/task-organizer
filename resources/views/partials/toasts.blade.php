{{-- Toast stack. Livewire: $this->dispatch('toast', message: '…', type: 'success'|'error'|'info'). Redirects: session()->flash('toast', [...]). --}}
<div x-data="{
        toasts: [],
        add(detail) {
            const toast = { id: Date.now() + Math.random(), message: detail.message, type: detail.type || 'success' }
            this.toasts.push(toast)
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== toast.id), 4500)
        },
    }"
     x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-full max-w-sm flex-col gap-2">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-3.5 text-sm shadow-lg dark:bg-slate-900"
             :class="{
                'border-emerald-200 dark:border-emerald-500/30': toast.type === 'success',
                'border-rose-200 dark:border-rose-500/30': toast.type === 'error',
                'border-slate-200 dark:border-slate-700': toast.type === 'info',
             }">
            <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                  :class="{ 'bg-emerald-500': toast.type === 'success', 'bg-rose-500': toast.type === 'error', 'bg-indigo-500': toast.type === 'info' }"></span>
            <p class="flex-1 text-slate-700 dark:text-slate-200" x-text="toast.message"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="toasts = toasts.filter(t => t.id !== toast.id)" aria-label="Dismiss">×</button>
        </div>
    </template>
</div>
