@props([
    'id',
    'label',
    'name' => null,
    'hint' => null,
])

<div>
    <label class="sb-label" for="{{ $id }}">{{ $label }}</label>
    <div class="sb-password-row mt-1 flex gap-2">
        <input
            id="{{ $id }}"
            type="password"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->merge(['class' => 'sb-input min-w-0 flex-1']) }}
        />
        <button
            type="button"
            class="sb-password-toggle sb-btn-ghost shrink-0"
            data-password-target="{{ $id }}"
            aria-label="Show password"
            aria-pressed="false"
        >
            <x-hashward.password-toggle-icons />
        </button>
    </div>
    @if ($hint)
        <p class="mt-1 text-xs text-(--color-sb-muted)">{{ $hint }}</p>
    @endif
</div>
