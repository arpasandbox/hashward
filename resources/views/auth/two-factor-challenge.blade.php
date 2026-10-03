@extends('layouts.app')

@section('title', 'Authenticator')

@section('content')
<x-hashward.auth-shell
    :step="2"
    title="Sign in"
    lead="Enter the 6-digit authenticator code or a recovery code."
>
    @if ($errors->any())
        <div class="sb-alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('two-factor.challenge') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="sb-label">Authenticator code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                class="sb-input font-mono tracking-widest">
        </div>
        <button type="submit" class="sb-btn-primary">Verify</button>
    </form>
</x-hashward.auth-shell>
@endsection
