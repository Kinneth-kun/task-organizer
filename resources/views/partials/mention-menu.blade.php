{{-- Suggestions for the "mentions" Alpine component (resources/js/app.js). --}}
<div x-show="open" x-cloak class="menu left-0 mt-1 w-72" @click.outside="open = false">
    <template x-for="(person, i) in results" :key="person.id">
        <button type="button" class="menu-item" :class="i === active ? 'bg-slate-50 dark:bg-slate-800' : ''" @mousedown.prevent="choose(person)">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-[10px] font-semibold text-white" x-text="person.initials"></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm" x-text="person.name"></span>
                <span class="block truncate text-xs text-slate-400" x-text="'@' + person.username"></span>
            </span>
        </button>
    </template>
</div>
