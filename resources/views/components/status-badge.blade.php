@props(['status'])

@php
    $styles = match($status) {
        'completed' => 'bg-green-50 text-green-700 ring-1 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/30',
        'enrolled' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30',
        'withdrawn' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
        'deferred' => 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-600/20 dark:bg-yellow-500/10 dark:text-yellow-400 dark:ring-yellow-500/30',
        default => 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {$styles}"]) }}>
    {{ ucfirst($status) }}
</span>
