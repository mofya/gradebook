@props(['grade' => null, 'size' => 'md'])

@php
    $styles = match(true) {
        $grade === null => 'bg-gray-50 text-gray-400 ring-gray-600/10 dark:bg-gray-800 dark:text-gray-500 dark:ring-gray-500/20',
        in_array($grade, ['A+', 'A']) => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
        in_array($grade, ['B+', 'B']) => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
        in_array($grade, ['C+', 'C']) => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
        default => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
    };

    $sizeClass = match($size) {
        'lg' => 'px-3 py-1 text-lg',
        'sm' => 'px-2 py-0.5 text-xs',
        default => 'px-2.5 py-1 text-base',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-lg font-bold ring-1 {$styles} {$sizeClass}"]) }}>
    {{ $grade ?? '—' }}
</span>
