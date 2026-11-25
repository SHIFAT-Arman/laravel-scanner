@props([
    'sidebar' => false,
])

<a {{ $attributes->class('inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ieee-blue') }}>
    <x-app-logo-icon class="h-9 w-auto" />
    <span class="sr-only">{{ config('app.name', 'IEEE AIUB Student Branch') }}</span>
</a>
