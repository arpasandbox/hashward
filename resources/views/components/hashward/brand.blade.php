@props([
    'href' => null,
    'logo' => false,
    'align' => 'center',
])

<a href="{{ $href ?? route('login') }}" @class([
    'inline-flex items-center gap-2.5 no-underline',
    'w-full justify-center' => $logo && $align === 'center',
    'justify-start' => $logo && $align === 'left',
    'justify-center' => ! $logo,
])>
    @if ($logo)
        <img
            src="{{ asset('images/hashward-logo.png') }}"
            alt=""
            width="40"
            height="40"
            class="sb-brand-logo sb-brand-logo--wordmark"
            decoding="async"
        />
        <span class="sb-brand sb-brand-wordmark">Hashward</span>
    @else
        <span class="sb-brand-mark" aria-hidden="true">H</span>
        <span class="sb-brand">Hashward</span>
    @endif
</a>
