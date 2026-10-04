@extends('layouts.app')

@section('title', 'Invite unavailable')

@section('content')
<x-hashward.auth-shell
    title="Invite unavailable"
    lead="This invite link is invalid, expired, or has already been used."
>
    <a href="{{ route('login') }}" class="sb-btn-primary inline-block text-center">Go to sign in</a>
</x-hashward.auth-shell>
@endsection
