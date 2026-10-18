<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Support\Settings::string('general.app_name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234f46e5'/%3E%3Cpath d='M9 16.5l4.5 4.5L23 11' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>
        // Apply the saved theme before first paint.
        (() => {
            let theme = null
            try { theme = localStorage.getItem('theme') } catch (e) {}
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        })()
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebar: false }">
@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
        ['route' => 'tasks.index', 'match' => 'tasks.*', 'label' => 'My Tasks', 'icon' => 'tasks'],
        ['route' => 'projects.index', 'match' => 'projects.*', 'label' => 'Projects', 'icon' => 'folder'],
        ['route' => 'calendar', 'match' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar'],
        ['route' => 'search', 'match' => 'search', 'label' => 'Search', 'icon' => 'search'],
    ];
    $unread = $user->unreadNotifications()->count();
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="sidebar = false"></div>

{{-- Sidebar --}}
<aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
       :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    <div class="flex h-16 items-center gap-2.5 border-b border-slate-100 px-5 dark:border-slate-800">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm"><x-icon name="check" class="h-5 w-5" /></span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Support\Settings::string('general.app_name') }}</p>
            <p class="truncate text-[11px] text-slate-400">{{ \App\Support\Settings::string('general.organization') }}</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                <x-icon :name="$item['icon']" class="h-5 w-5" /> {{ $item['label'] }}
            </a>
        @endforeach
        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <x-icon name="bell" class="h-5 w-5" /> Activity Logs
            @if ($unread)
                <span class="ml-auto rounded-full bg-slate-700 px-1.5 py-0.5 text-[10px] font-semibold text-white dark:bg-slate-200 dark:text-slate-900">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </a>

        @can('full-access')
            <p class="nav-section">Oversight</p>
            <a href="{{ route('executive') }}" class="nav-link {{ request()->routeIs('executive') ? 'active' : '' }}"><x-icon name="chart" class="h-5 w-5" /> Executive dashboard</a>
            <a href="{{ route('admin.activity') }}" class="nav-link {{ request()->routeIs('admin.activity') ? 'active' : '' }}"><x-icon name="history" class="h-5 w-5" /> Audit trail</a>
        @endcan

        @can('admin')
            <p class="nav-section">Administration</p>
            <a href="{{ route('meeting') }}" class="nav-link" target="_blank"><x-icon name="presentation" class="h-5 w-5" /> Meeting mode</a>
            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><x-icon name="users" class="h-5 w-5" /> Users</a>
            <a href="{{ route('admin.departments') }}" class="nav-link {{ request()->routeIs('admin.departments') ? 'active' : '' }}"><x-icon name="building" class="h-5 w-5" /> Departments</a>
            <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}"><x-icon name="cog" class="h-5 w-5" /> Settings</a>
        @endcan
    </nav>

    <div class="border-t border-slate-100 p-3 dark:border-slate-800">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <x-avatar :user="$user" size="h-9 w-9 text-xs" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->role->label() }}{{ $user->department ? ' · '.$user->department->code : '' }}</p>
            </div>
        </div>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-64">
    {{-- Top bar --}}
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/85 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/85">
        <button type="button" class="icon-btn lg:hidden" @click="sidebar = true" aria-label="Open menu"><x-icon name="menu" class="h-6 w-6" /></button>

        <form action="{{ route('search') }}" method="GET" class="relative hidden max-w-md flex-1 sm:block">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search tasks, projects, comments…" class="input pl-9">
        </form>

        <div class="ml-auto flex items-center gap-1.5">
            <livewire:quick-create />
            <livewire:notifications.notification-bell />

            <div class="relative" x-data="{ open: false, dark: document.documentElement.classList.contains('dark') }" @click.outside="open = false">
                <button type="button" class="flex items-center rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="open = !open" aria-label="Account menu">
                    <x-avatar :user="$user" size="h-8 w-8 text-xs" />
                </button>
                <div x-show="open" x-cloak x-transition class="menu right-0 w-56">
                    <div class="border-b border-slate-100 px-3.5 py-2.5 dark:border-slate-800">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ '@'.$user->username }}</p>
                    </div>
                    <a href="{{ route('profile') }}" class="menu-item"><x-icon name="user" class="h-4 w-4" /> Profile & preferences</a>
                    <button type="button" class="menu-item" @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light') } catch (e) {}">
                        <x-icon name="moon" class="h-4 w-4" x-show="!dark" />
                        <x-icon name="sun" class="h-4 w-4" x-show="dark" x-cloak />
                        <span x-text="dark ? 'Light mode' : 'Dark mode'"></span>
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="menu-item text-rose-600 dark:text-rose-400"><x-icon name="logout" class="h-4 w-4" /> Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            {{ $slot }}
        </div>
    </main>
</div>

@include('partials.toasts')
@include('partials.confirm')
</body>
</html>
