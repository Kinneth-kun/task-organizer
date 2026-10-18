import './bootstrap'

/*
 * In-app confirmation dialog (resources/views/partials/confirm.blade.php), used instead of the
 * browser's confirm() so no "localhost says" pop-up appears.
 *
 *   window.appConfirm('Delete this task? Its history is kept.').then(ok => ...)
 *
 * The first sentence becomes the title and the rest the message. Messages starting with a
 * destructive verb get a red button labelled with that verb.
 */
const DESTRUCTIVE = ['Delete', 'Remove', 'Deactivate', 'Clear']

function describe(text) {
    let [, title, message] = text.match(/^(.*?[?.!])\s+([\s\S]+)$/) ?? [null, text, '']
    let verb = DESTRUCTIVE.find((word) => text.startsWith(word + ' '))

    return { title, message, danger: !!verb, confirmText: verb ?? 'Confirm', cancelText: 'Cancel' }
}

window.appConfirm = (text, options = {}) =>
    new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('confirm-dialog', { detail: { ...describe(text), ...options, resolve } }))
    })

document.addEventListener('alpine:init', () => {
    window.Alpine.data('confirmDialog', () => ({
        open: false,
        title: '',
        message: '',
        danger: false,
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        resolve: null,

        show(detail) {
            // A new question replaces any one still open.
            this.answer(false)
            Object.assign(this, detail)
            this.open = true
            this.$nextTick(() => this.$refs.confirm.focus())
        },

        answer(ok) {
            let resolve = this.resolve
            this.resolve = null
            this.open = false
            if (resolve) resolve(ok)
        },
    }))
})

document.addEventListener('livewire:init', () => {
    // Route wire:confirm through the in-app dialog. Runs after Livewire's own confirm directive
    // has set el.__livewire_confirm, and replaces it.
    window.Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm' || directive.modifiers.includes('prompt')) return

        let text = directive.expression.replaceAll('\\n', '\n') || 'Are you sure?'
        el.__livewire_confirm = (action) => {
            window.appConfirm(text).then((ok) => ok && action())
        }
    })

    // Livewire's own "page has expired" and "empty response" questions also use confirm().
    let asking = false
    let askToRefresh = (text) => {
        if (asking) return
        asking = true
        window.appConfirm(text, { confirmText: 'Refresh page' }).then((ok) => {
            asking = false
            if (ok) window.location.reload()
        })
    }

    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, body, preventDefault }) => {
            if (response.status === 419) {
                preventDefault()
                askToRefresh('This page has expired. Refresh the page to continue.')
            } else if (response.ok && (body ?? '').trim() === '') {
                preventDefault()
                askToRefresh('The server returned an empty response. Refresh the page to continue.')
            }
        })
    })
})

/*
 * @mention autocomplete for remark and comment boxes.
 *
 *   <div x-data="mentions" class="relative">
 *     <textarea x-ref="input" @input="onInput" @keydown="onKeydown" wire:model="body"></textarea>
 *     @include('partials.mention-menu')
 *   </div>
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('mentions', () => ({
        open: false,
        results: [],
        active: 0,
        start: null,
        timer: null,

        onInput() {
            let input = this.$refs.input
            let before = input.value.slice(0, input.selectionStart)
            let match = before.match(/(?:^|[\s(])@([a-z0-9._-]*)$/i)

            if (!match) {
                this.open = false
                return
            }

            this.start = input.selectionStart - match[1].length - 1
            clearTimeout(this.timer)
            this.timer = setTimeout(() => this.search(match[1]), 150)
        },

        async search(term) {
            try {
                let response = await fetch(`/lookup/users?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                this.results = response.ok ? await response.json() : []
            } catch (e) {
                this.results = []
            }
            this.active = 0
            this.open = this.results.length > 0
        },

        onKeydown(event) {
            if (!this.open) return

            if (event.key === 'ArrowDown') {
                event.preventDefault()
                this.active = (this.active + 1) % this.results.length
            } else if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.active = (this.active - 1 + this.results.length) % this.results.length
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault()
                this.choose(this.results[this.active])
            } else if (event.key === 'Escape') {
                this.open = false
            }
        },

        choose(person) {
            if (!person) return

            let input = this.$refs.input
            let cursor = input.selectionStart
            let insert = `@${person.username} `
            input.value = input.value.slice(0, this.start) + insert + input.value.slice(cursor)

            let position = this.start + insert.length
            input.setSelectionRange(position, position)
            input.focus()
            // Let wire:model pick up the change.
            input.dispatchEvent(new Event('input', { bubbles: true }))
            this.open = false
        },
    }))
})
