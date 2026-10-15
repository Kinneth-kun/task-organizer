<x-layouts.guest title="Sign in">
    <div class="flex min-h-full items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/30"><x-icon name="check" class="h-7 w-7" /></span>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ \App\Support\Settings::string('general.app_name') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ \App\Support\Settings::string('general.organization') }} · Sign in to continue</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="card space-y-5 p-6 sm:p-8">
                @csrf
                @if ($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="login" class="label">Username or email</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" class="input" autocomplete="username" required autofocus>
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="label">Password</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="input pr-10" autocomplete="current-password" required>
                        <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                            <x-icon name="eye" class="h-4 w-4" x-show="!show" />
                            <x-icon name="eye-slash" class="h-4 w-4" x-show="show" x-cloak />
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="checkbox"> Keep me signed in
                </label>

                <button type="submit" class="btn btn-primary w-full py-2.5">Sign in</button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">Forgot your password? Ask an administrator to reset it.</p>
        </div>
    </div>
</x-layouts.guest>
