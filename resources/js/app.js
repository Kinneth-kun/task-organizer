import './bootstrap'

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
