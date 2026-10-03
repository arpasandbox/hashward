@extends('layouts.app')

@section('title', 'Vault')

@section('content')
<div id="vault-boot-error" class="sb-alert-error mx-4 mt-4 hidden" role="alert" hidden></div>

<div id="vault-app" class="sb-vault-shell"
    data-user-name="{{ $userName }}"
    data-logout-url="{{ route('logout') }}"
    data-kdf-salt="{{ $kdfSalt }}"
    data-kdf-iterations="{{ $kdfIterations }}"
    data-vault-check="{{ $vaultCheck }}"
>

    <div id="unlock-screen" class="sb-auth-wrap">
        <div class="mb-8 text-center">
            <x-hashward.brand :href="route('vault')" :logo="true" />
        </div>
        <div class="sb-panel">
            <h1 class="mb-2 text-2xl font-semibold">Unlock your vault</h1>
            <p class="sb-lead">Hello, {{ $userName }}. Your master password stays in this browser. It is never sent to the server.</p>
            <div id="unlock-error" class="sb-alert-error hidden" role="alert" hidden></div>
            <form id="unlock-form" class="space-y-4" action="#" method="post">
                <div>
                    <label for="unlock-master" class="sb-label">Master password</label>
                    <input id="unlock-master" type="password" autocomplete="off" required class="sb-input">
                </div>
                <button type="submit" id="unlock-submit" class="sb-btn-primary">Unlock</button>
                <p id="unlock-status" class="hidden text-center text-sm text-(--color-sb-muted)" role="status"></p>
            </form>
            <p class="sb-note">Forgot it? Nobody can recover it for you, by design.</p>
        </div>
    </div>

    <div id="vault-screen" class="hidden flex min-h-screen flex-col">
        <header class="sb-vault-top lg:hidden">
            <div class="sb-vault-top-inner sb-vault-top-inner--centered">
                <x-hashward.brand :href="route('vault')" :logo="true" />
            </div>
        </header>
        <div id="vault-api-error" class="sb-alert-error mx-4 mt-2 hidden" role="alert" hidden></div>

        <div class="sb-vault-grid flex-1">
            <aside id="vault-sidebar" class="sb-sidebar hidden lg:block" aria-label="Vault actions">
                <div class="sb-sidebar-brand">
                    <x-hashward.brand :href="route('vault')" :logo="true" />
                </div>

                <p class="sb-sidebar-title">Actions</p>
                <button type="button" id="btn-generator" class="sb-btn-ghost mb-2 w-full">Password generator</button>
                <button type="button" id="btn-import" class="sb-btn-ghost mb-2 w-full">Import CSV</button>
                <button type="button" id="btn-export" class="sb-btn-ghost mb-2 w-full">Export encrypted</button>

                <p class="sb-sidebar-title mt-6">Account</p>
                <button type="button" id="btn-change-master" class="sb-btn-ghost mb-2 w-full">Change master password</button>
                <button type="button" id="btn-lock" class="sb-btn-accent mb-2 w-full">Lock vault</button>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sb-btn-danger w-full">Sign out</button>
                </form>
            </aside>

            <div class="sb-vault-main">
                <div class="sb-vault-main-bar">
                    <nav class="sb-vault-nav" aria-label="Vault views">
                        <button type="button" id="nav-all" class="sb-nav-item is-active">All items</button>
                        <button type="button" id="nav-favorites" class="sb-nav-item">Favorites</button>
                        <button type="button" id="nav-trash" class="sb-nav-item">Trash</button>
                    </nav>
                    <div class="sb-vault-toolbar">
                        <input id="search-input" type="search" placeholder="Search title, username, tags…" aria-label="Search vault entries"
                            class="sb-input">
                        <button type="button" id="btn-new" class="sb-btn-primary sb-btn-new">
                            <svg class="sb-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>
                            <span>Add new</span>
                        </button>
                    </div>
                </div>
                <p id="empty-state" class="hidden py-8 text-center text-sm text-(--color-sb-muted)">No entries match.</p>
                <div id="vault-table-wrap" class="sb-table-wrap hidden" aria-live="polite">
                    <table class="sb-vault-table" aria-label="Vault entries">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Username</th>
                                <th scope="col">Website</th>
                                <th scope="col">Tags</th>
                                <th scope="col" class="sb-table-col-date">Updated</th>
                            </tr>
                        </thead>
                        <tbody id="item-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="mobile-sheet-scrim" class="sb-sheet-scrim lg:hidden" hidden aria-hidden="true"></div>

        <nav class="sb-mobile-bar lg:hidden" aria-label="Vault menu">
            <button type="button" id="btn-mobile-actions" class="sb-mobile-bar-btn"
                aria-controls="mobile-actions-panel" aria-expanded="false">
                <svg class="sb-mobile-bar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                </svg>
                <span>Actions</span>
            </button>
            <button type="button" id="btn-mobile-account" class="sb-mobile-bar-btn"
                aria-controls="mobile-account-panel" aria-expanded="false">
                <svg class="sb-mobile-bar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21a8 8 0 0 1 16 0" />
                </svg>
                <span>Account</span>
            </button>
        </nav>

        <div id="mobile-actions-panel" class="sb-sheet-panel lg:hidden" role="region" aria-labelledby="mobile-actions-title" hidden>
            <div class="sb-sheet-body">
                <h2 id="mobile-actions-title" class="sb-sheet-title">Actions</h2>
                <button type="button" id="btn-mobile-generator" class="sb-btn-ghost w-full">Password generator</button>
                <button type="button" id="btn-mobile-import" class="sb-btn-ghost w-full">Import CSV</button>
                <button type="button" id="btn-mobile-export" class="sb-btn-ghost w-full">Export encrypted</button>
            </div>
        </div>

        <div id="mobile-account-panel" class="sb-sheet-panel lg:hidden" role="region" aria-labelledby="mobile-account-title" hidden>
            <div class="sb-sheet-body">
                <h2 id="mobile-account-title" class="sb-sheet-title">Account</h2>
                <button type="button" id="btn-mobile-change-master" class="sb-btn-ghost w-full">Change master password</button>
                <button type="button" id="btn-mobile-lock" class="sb-btn-accent w-full">Lock vault</button>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sb-btn-danger w-full">Sign out</button>
                </form>
            </div>
        </div>
    </div>

    <dialog id="detail-dialog" class="sb-dialog" aria-labelledby="detail-title">
        <div class="sb-dialog-body sb-detail-dialog-body">
            <div class="sb-detail-dialog-top">
                <button type="button" id="detail-close" class="sb-dialog-close" aria-label="Close">
                    <svg class="sb-dialog-close-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 id="detail-title" class="sb-detail-title"></h2>
                    <p id="detail-meta" class="sb-detail-meta"></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="detail-edit" class="sb-btn-ghost">Edit</button>
                    <button type="button" id="detail-delete" class="sb-btn-danger">Delete</button>
                </div>
            </div>

            <div class="sb-field-row">
                <p class="sb-field-label">Username</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <p id="detail-username" class="sb-field-value flex-1"></p>
                    <button type="button" id="detail-copy-user" class="sb-btn-ghost">Copy</button>
                </div>
            </div>

            <div class="sb-field-row">
                <p class="sb-field-label">Password</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <p id="detail-password" class="sb-field-value mono flex-1 tracking-widest">••••••••••••</p>
                    <button type="button" id="detail-toggle-pass" class="sb-btn-ghost">Show</button>
                    <button type="button" id="detail-copy-pass" class="sb-btn-ghost">Copy</button>
                </div>
            </div>

            <div class="sb-field-row">
                <p class="sb-field-label">Website</p>
                <p id="detail-url" class="sb-field-value"></p>
            </div>

            <div class="sb-field-row">
                <p class="sb-field-label">Notes</p>
                <p id="detail-notes" class="sb-field-value whitespace-pre-wrap"></p>
            </div>
        </div>
    </dialog>

    <dialog id="entry-dialog" class="sb-dialog">
        <form id="entry-form" class="sb-dialog-body space-y-3">
            <h2 id="entry-dialog-title" class="text-lg font-semibold">Entry</h2>
            <input type="hidden" id="entry-id">
            <div>
                <label class="sb-label" for="entry-title">Title</label>
                <input id="entry-title" required class="sb-input mt-1">
            </div>
            <div>
                <label class="sb-label" for="entry-url">Website</label>
                <input id="entry-url" type="url" class="sb-input mt-1">
            </div>
            <div>
                <label class="sb-label" for="entry-username">Username</label>
                <input id="entry-username" autocomplete="off" class="sb-input mt-1">
            </div>
            <div>
                <label class="sb-label" for="entry-password">Password</label>
                <div class="sb-entry-password-row mt-1 flex gap-2">
                    <input id="entry-password" type="password" autocomplete="new-password" class="sb-input min-w-0 flex-1">
                    <button type="button" id="entry-password-toggle" class="sb-btn-ghost shrink-0">Show</button>
                </div>
            </div>
            <div>
                <label class="sb-label" for="entry-notes">Notes</label>
                <textarea id="entry-notes" rows="3" class="sb-input mt-1"></textarea>
            </div>
            <div>
                <label class="sb-label" for="entry-tags">Tags (comma-separated)</label>
                <input id="entry-tags" class="sb-input mt-1">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input id="entry-favorite" type="checkbox" class="rounded border-(--color-sb-border)">
                Favorite
            </label>
            <div id="entry-save-error" class="sb-alert-error hidden" role="alert" hidden></div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="entry-cancel" class="sb-btn-ghost">Cancel</button>
                <button type="button" id="entry-save" class="sb-btn-primary w-auto">Save</button>
            </div>
        </form>
    </dialog>

    <dialog id="generator-dialog" class="sb-dialog sb-dialog-md">
        <div class="sb-dialog-body">
            <h2 class="mb-4 text-lg font-semibold">Password generator</h2>
            <output id="generated-password" class="sb-generator-output"></output>
            <div class="sb-generator-length-row">
                <label for="gen-length" class="sb-label mb-0">Length</label>
                <input id="gen-length" type="number" min="8" max="128" value="20" class="sb-input sb-generator-length">
            </div>
            <div class="mt-4 space-y-1 text-sm text-(--color-sb-muted)">
                <label class="flex items-center gap-2"><input id="gen-lower" type="checkbox" checked> Lowercase</label>
                <label class="flex items-center gap-2"><input id="gen-upper" type="checkbox" checked> Uppercase</label>
                <label class="flex items-center gap-2"><input id="gen-digits" type="checkbox" checked> Digits</label>
                <label class="flex items-center gap-2"><input id="gen-symbols" type="checkbox" checked> Symbols</label>
                <label class="flex items-center gap-2"><input id="gen-passphrase" type="checkbox"> Passphrase mode</label>
            </div>
            <div class="sb-generator-actions">
                <div class="sb-generator-actions-primary">
                    <button type="button" id="gen-regenerate" class="sb-btn-primary">Regenerate</button>
                    <button type="button" id="gen-copy" class="sb-btn-ghost">Copy</button>
                </div>
                <button type="button" id="gen-close" class="sb-btn-ghost">Close</button>
            </div>
        </div>
    </dialog>

    <input type="file" id="import-file" accept=".csv,text/csv" class="hidden">
</div>
@endsection
