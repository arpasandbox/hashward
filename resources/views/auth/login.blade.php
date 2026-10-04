@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<x-hashward.auth-shell
    :step="1"
    title="Sign in"
    lead="This is your account login, not your master password."
>
    @if (session('status'))
        <div class="sb-alert-success mb-4" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="sb-alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="sb-label">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required
                value="{{ old('email') }}" class="sb-input">
        </div>
        <x-hashward.password-field
            id="password"
            name="password"
            label="Account password"
            autocomplete="current-password"
            required
        />
        <button type="submit" class="sb-btn-primary">Continue</button>
    </form>

    <p class="sb-note">After 5 failed attempts this account locks for 15 minutes.</p>
</x-hashward.auth-shell>
@endsection
