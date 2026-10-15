@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ \App\Support\Settings::string('general.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>
        (() => {
            let theme = null
            try { theme = localStorage.getItem('theme') } catch (e) {}
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        })()
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gradient-to-br from-indigo-50 via-slate-50 to-white dark:from-slate-950 dark:via-slate-950 dark:to-indigo-950/40">
    {{ $slot }}
    @livewireScripts
</body>
</html>
