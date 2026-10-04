@extends('layouts.app')

@section('title', 'Invites')

@section('content')
<main id="main" class="sb-vault-shell" data-invites-page>
    <header class="sb-vault-top">
        <div class="sb-vault-top-inner">
            <x-hashward.brand :href="route('vault')" :logo="true" />
        </div>
    </header>

    <div class="sb-page-main mx-auto w-full min-w-0 max-w-[84rem]">
        <h1 class="sb-page-title mb-2 text-2xl font-semibold">Invite users</h1>
        <p class="mb-6 text-sm text-(--color-sb-muted)">Share a one-time link. Invites expire after {{ \App\Models\UserInvite::EXPIRY_DAYS }} days.</p>

        @if (session('status'))
            <div class="sb-alert-success mb-4" role="status">{{ session('status') }}</div>
        @endif

        @if (session('invite_url'))
            <div class="sb-panel mb-6 space-y-3" role="region" aria-label="New invite link">
                <p class="text-sm font-medium">Copy this link now. It is shown only once.</p>
                <div class="sb-invite-copy-row">
                    <input type="text" readonly value="{{ session('invite_url') }}" id="invite-url" class="sb-input font-mono text-xs" autocomplete="off" spellcheck="false">
                    <button type="button" class="sb-btn-primary sb-btn-primary--inline inline-flex items-center gap-1.5" id="copy-invite-url">
                        <x-heroicon-o-clipboard class="h-4 w-4 shrink-0" />
                        Copy link
                    </button>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="sb-alert-error mb-4" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('settings.invites.store') }}" class="sb-panel mb-8">
            @csrf
            <label for="invite-email" class="sb-label">Email address</label>
            <div class="sb-invite-create-row mt-1">
                <input id="invite-email" name="email" type="email" required autocomplete="off" class="sb-input"
                    value="{{ old('email') }}">
                <button type="submit" class="sb-btn-primary sb-btn-primary--inline">Create invite link</button>
            </div>
        </form>

        <h2 class="mb-3 text-lg font-semibold">Invites</h2>
        <div class="sb-table-wrap sb-invites-table-wrap rounded-xl border border-(--color-sb-border)">
            <table class="sb-invites-table sb-audit-table w-full max-w-full text-left text-sm">
                <thead class="bg-(--color-sb-surface-2) text-(--color-sb-muted)">
                    <tr>
                        <th class="px-4 py-2 font-medium">Email</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Expires</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invites as $invite)
                        @php
                            $inviteInactive = (bool) $invite->revoked_at
                                || ($invite->expires_at->isPast() && ! $invite->accepted_at);
                        @endphp
                        <tr @class([
                            'sb-invites-row border-t border-(--color-sb-border)',
                            'sb-invites-row--inactive' => $inviteInactive,
                        ])>
                            <td class="sb-invites-cell sb-invites-cell-email px-4 py-2" data-label="Email">{{ $invite->email }}</td>
                            <td class="sb-invites-cell px-4 py-2" data-label="Status">
                                <span class="sb-invites-cell-value">
                                    @if ($invite->accepted_at)
                                        Accepted
                                    @elseif ($invite->revoked_at)
                                        Revoked
                                    @elseif ($invite->expires_at->isPast())
                                        Expired
                                    @else
                                        Pending
                                    @endif
                                </span>
                            </td>
                            <td class="sb-invites-cell sb-invites-cell-expires px-4 py-2" data-label="Expires">
                                <span class="sb-invites-cell-value">{{ $invite->expires_at->format('Y-m-d H:i') }}</span>
                            </td>
                            <td @class([
                                'sb-invites-cell sb-invites-cell-actions px-4 py-2 text-right',
                                'sb-invites-cell-actions--empty' => ! $invite->isPending() && ! $invite->revoked_at,
                            ])>
                                <div class="sb-invites-actions">
                                    @if ($invite->isPending())
                                        <button type="button"
                                            class="sb-btn-ghost invite-view-link-btn text-sm"
                                            data-link-endpoint="{{ route('settings.invites.link', $invite) }}">
                                            View link
                                        </button>
                                        <form method="post" action="{{ route('settings.invites.destroy', $invite) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sb-btn-ghost text-sm">Revoke</button>
                                        </form>
                                    @elseif ($invite->revoked_at)
                                        <form method="post" action="{{ route('settings.invites.destroy', $invite) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sb-btn-ghost text-sm">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="sb-invites-empty">
                            <td colspan="4" class="px-4 py-6 text-(--color-sb-muted)">No invites yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <a href="{{ route('vault') }}" class="sb-btn-ghost inline-flex items-center gap-1.5">
                <x-heroicon-o-arrow-left class="h-4 w-4 shrink-0" />
                Back to vault
            </a>
        </div>
    </div>

    <dialog id="invite-link-dialog" class="sb-dialog sb-dialog-md" aria-labelledby="invite-link-dialog-title">
        <div class="sb-dialog-body">
            <div class="mb-4 flex items-start justify-between gap-3">
                <h2 id="invite-link-dialog-title" class="text-lg font-semibold">Invite link</h2>
                <button type="button" id="invite-link-dialog-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
                </button>
            </div>
            <p class="mb-3 text-sm text-(--color-sb-muted)">Opening this generates a fresh link. Any older link for this invite stops working.</p>
            <div class="sb-invite-copy-row">
                <input type="text" readonly id="invite-link-modal-input" class="sb-input font-mono text-xs" autocomplete="off" spellcheck="false">
                <button type="button" class="sb-btn-primary sb-btn-primary--inline inline-flex items-center gap-1.5" id="invite-link-modal-copy">
                    <x-heroicon-o-clipboard class="h-4 w-4 shrink-0" />
                    Copy
                </button>
            </div>
            <p id="invite-link-dialog-error" class="sb-alert-error mt-3 hidden" role="alert" hidden></p>
        </div>
    </dialog>
</main>
@endsection
