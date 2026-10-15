@props(['user', 'size' => 'h-7 w-7 text-[11px]', 'title' => null])
@if ($user)
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold ring-2 ring-white dark:ring-slate-900 '.$size.' '.\App\Support\Palette::solid($user->avatar_color)]) }}
          title="{{ $title ?? $user->name }}">{{ $user->initials() }}</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400 dark:border-slate-600 '.$size]) }} title="Unassigned">?</span>
@endif
