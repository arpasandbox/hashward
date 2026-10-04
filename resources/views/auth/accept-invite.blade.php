@extends('layouts.app')

@section('title', 'Accept invite')

@section('content')
<x-hashward.auth-shell
    title="Create your account"
    lead="You were invited to this vault. Choose a display name and account password (not your master password yet)."
>
    @if ($errors->any())
        <div class="sb-alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('invites.store', $token) }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="sb-label">Email</label>
            <input id="email" type="email" readonly value="{{ $email }}" class="sb-input bg-(--color-sb-surface-2)">
        </div>
        <div>
            <label for="name" class="sb-label">Your name</label>
            <input id="name" name="name" type="text" autocomplete="name" required
                value="{{ old('name') }}" class="sb-input">
        </div>
        <x-hashward.password-field
            id="password"
            name="password"
            label="Account password"
            hint="At least 12 characters. You will set up two-factor auth and your master password after sign-in."
            autocomplete="new-password"
            required
            minlength="12"
        />
        <button type="submit" class="sb-btn-primary">Create account</button>
    </form>
</x-hashward.auth-shell>
@endsection
