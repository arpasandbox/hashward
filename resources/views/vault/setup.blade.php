@extends('layouts.app')

@section('title', 'Master password')

@section('content')
<main id="main" class="sb-auth-wrap" data-kdf-iterations="{{ $kdfIterations }}"
    data-setup-url="{{ url('/api/crypto/setup') }}" data-setup-root>
    <div class="mb-8 text-center">
        <x-hashward.brand :href="route('vault.setup')" :logo="true" />
    </div>

    <div class="sb-panel">
        <p class="sb-step">Step 3 of 3</p>
        <h1 class="mb-2 text-2xl font-semibold">Create master password</h1>
        <p class="sb-lead">Your master password stays in this browser. It is never sent to the server.</p>

        @if (session('recovery_codes_plain'))
            <div class="mb-6 rounded-lg border border-(--color-sb-accent-dim) bg-(--color-sb-surface-2) p-4 text-sm">
                <p class="mb-2 font-medium text-(--color-sb-accent)">Save these recovery codes now (shown once):</p>
                <ul class="font-mono text-xs leading-relaxed text-(--color-sb-muted)">
                    @foreach (session('recovery_codes_plain') as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="setup-error" class="sb-alert-error hidden" role="alert"></div>
        <noscript>
            <p class="sb-alert-error">JavaScript is required to create your master password.</p>
        </noscript>

        <form id="master-setup-form" class="space-y-4" action="#" method="post">
            <div>
                <label for="master" class="sb-label">Master password</label>
                <input id="master" type="password" autocomplete="new-password" required minlength="12" class="sb-input">
            </div>
            <div>
                <label for="master_confirm" class="sb-label">Confirm master password</label>
                <input id="master_confirm" type="password" autocomplete="new-password" required minlength="12" class="sb-input">
            </div>
            <button type="submit" class="sb-btn-primary">Save and open vault</button>
        </form>

        <p class="sb-note">Forgot it later? Nobody can recover it for you, by design.</p>
    </div>
</main>
@endsection
