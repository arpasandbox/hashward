@props([
    'href' => null,
    'logo' => false,
])

<a href="{{ $href ?? route('login') }}" @class([
    'inline-flex items-center no-underline',
    'justify-center' => $logo,
])>
    @if ($logo)
        <img
            src="{{ asset('images/hashward-logo.png') }}"
            alt="Hashward"
            width="160"
            height="160"
            class="sb-brand-logo"
            decoding="async"
        />
    @else
        <span class="sb-brand-mark" aria-hidden="true">H</span>
        <span class="sb-brand">Hashward</span>
    @endif
</a>
