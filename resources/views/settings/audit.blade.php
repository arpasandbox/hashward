@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
<main id="main" class="sb-vault-shell">
    <header class="sb-vault-top">
        <div class="sb-vault-top-inner">
            <x-hashward.brand :href="route('vault')" :logo="true" />
        </div>
    </header>

    <div class="sb-page-main mx-auto w-full min-w-0 max-w-[112rem]">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="sb-page-title mb-0 text-2xl font-semibold">Audit log</h1>
            <a href="{{ route('vault') }}" class="sb-btn-ghost inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                <x-heroicon-o-arrow-left class="h-4 w-4 shrink-0" />
                Back to vault
            </a>
        </div>

        <div class="sb-table-wrap sb-audit-log-wrap rounded-xl border border-(--color-sb-border)">
            <table class="sb-audit-table sb-audit-log-table w-full max-w-full text-left text-sm">
                <thead class="bg-(--color-sb-surface-2) text-(--color-sb-muted)">
                    <tr>
                        <th class="sb-audit-log-col-time font-medium">Time</th>
                        <th class="sb-audit-log-col-event font-medium">Event</th>
                        <th class="sb-audit-log-col-ip font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-(--color-sb-border)">
                            <td class="sb-audit-log-col-time">
                                <span class="hidden lg:inline">{{ $log->created_at?->format('Y-m-d H:i:s') }}</span>
                                @if ($log->created_at)
                                    <span class="sb-audit-log-time-stacked lg:hidden">
                                        <span class="block">{{ $log->created_at->format('Y-m-d') }}</span>
                                        <span class="block text-(--color-sb-muted)">{{ $log->created_at->format('H:i:s') }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="sb-audit-log-col-event">{{ $log->event }}</td>
                            <td class="sb-audit-log-col-ip font-mono">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="sb-audit-log-empty text-(--color-sb-muted)">No events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links('pagination.hashward') }}
    </div>
</main>
@endsection
