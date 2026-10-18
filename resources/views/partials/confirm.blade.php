{{-- In-app confirmation dialog, used instead of the browser's "localhost says" pop-up. Opened by window.appConfirm() in resources/js/app.js, which every wire:confirm goes through. --}}
<div x-data="confirmDialog" x-show="open" x-cloak x-transition.opacity.duration.100ms
     @confirm-dialog.window="show($event.detail)" @keydown.escape.window="open && answer(false)" @click.self="answer(false)"
     class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 pt-[15vh] backdrop-blur-sm"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="card w-full max-w-md shadow-xl">
        <div class="card-body flex gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="alert" class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1 pt-0.5">
                <h2 id="confirm-title" class="text-base font-semibold text-slate-900 dark:text-white" x-text="title"></h2>
                <p id="confirm-message" x-show="message" class="mt-1 text-sm whitespace-pre-line text-slate-600 dark:text-slate-300" x-text="message"></p>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
            <button type="button" class="btn btn-secondary" @click="answer(false)" x-text="cancelText"></button>
            <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" @click="answer(true)" x-ref="confirm" x-text="confirmText"></button>
        </div>
    </div>
</div>
