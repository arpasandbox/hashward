@props([
    'name',
])

@php
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
@endphp

<div {{ $attributes->class(['sb-user-badge']) }}>
    <span class="sb-user-badge-initial" title="{{ $name }}" aria-label="{{ $name }}">{{ $initial }}</span>
</div>
