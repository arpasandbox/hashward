@extends('layouts.app')

@section('title', 'Set up 2FA')

@section('content')
<x-hashward.auth-shell
    :step="2"
    title="Sign in"
    lead="Add this secret to your authenticator app. Account: {{ $email }}"
>
    <p class="mb-4 break-all rounded-lg border border-(--color-sb-border) bg-(--color-sb-surface-2) p-3 font-mono text-sm">{{ $secret }}</p>

    @if ($errors->any())
        <div class="sb-alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('two-factor.setup.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="sb-label">Authenticator code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required class="sb-input">
        </div>
        <button type="submit" class="sb-btn-primary">Confirm 2FA</button>
    </form>
</x-hashward.auth-shell>
@endsection
