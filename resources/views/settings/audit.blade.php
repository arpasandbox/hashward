@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
<main id="main" class="sb-vault-shell">
    <header class="sb-vault-top">
        <div class="sb-vault-top-inner">
            <x-hashward.brand :href="route('vault')" :logo="true" />
            <a href="{{ route('vault') }}" class="sb-btn-ghost ml-auto">Back to vault</a>
        </div>
    </header>

    <div class="sb-page-main mx-auto max-w-4xl">
        <h1 class="sb-page-title mb-6 text-2xl font-semibold">Audit log</h1>

        <div class="sb-table-wrap rounded-xl border border-(--color-sb-border)">
            <table class="sb-audit-table min-w-full text-left text-sm">
                <thead class="bg-(--color-sb-surface-2) text-(--color-sb-muted)">
                    <tr>
                        <th class="px-4 py-2 font-medium">Time</th>
                        <th class="px-4 py-2 font-medium">Event</th>
                        <th class="px-4 py-2 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-(--color-sb-border)">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2">{{ $log->event }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-(--color-sb-muted)">No events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
