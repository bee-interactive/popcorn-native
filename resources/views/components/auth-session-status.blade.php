@props([
    'status',
    'error',
])

@if ($status)
    <flux:callout variant="success" icon="x-circle" heading="{{ $status }}" />
@endif

@if ($error)
    <flux:callout variant="danger" icon="x-circle" heading="{{ $error }}" />
@endif
