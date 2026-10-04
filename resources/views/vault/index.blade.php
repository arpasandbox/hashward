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
                <x-hashward.password-field
                    id="unlock-master"
                    label="Master password"
                    autocomplete="off"
                    required
                />
                <button type="submit" id="unlock-submit" class="sb-btn-primary">Unlock</button>
                <p id="unlock-status" class="hidden text-center text-sm text-(--color-sb-muted)" role="status"></p>
            </form>
            <p class="sb-note">Forgot it? Nobody can recover it for you, by design.</p>
        </div>
    </div>

    <div id="vault-screen" class="hidden flex min-h-screen flex-col">
        <header class="sb-vault-top lg:hidden">
            <div class="sb-vault-top-inner sb-vault-top-inner--centered py-3">
                <x-hashward.brand :href="route('vault')" :logo="true" />
            </div>
        </header>
        <div id="vault-api-error" class="sb-alert-error mx-4 mt-2 hidden" role="alert" hidden></div>

        <div class="sb-vault-grid flex-1">
            <aside id="vault-sidebar" class="sb-sidebar hidden lg:flex" aria-label="Vault actions">
                <div class="sb-sidebar-main">
                    <div class="sb-sidebar-brand">
                        <x-hashward.brand :href="route('vault')" :logo="true" align="left" />
                    </div>

                    <p class="sb-sidebar-title">Actions</p>
                    <nav class="sb-sidebar-nav" aria-label="Actions">
                        <button type="button" id="btn-generator" class="sb-sidebar-link">
                            <x-heroicon-o-sparkles class="sb-sidebar-link-icon" />
                            Password generator
                        </button>
                        <button type="button" id="btn-import" class="sb-sidebar-link">
                            <x-heroicon-o-arrow-up-tray class="sb-sidebar-link-icon" />
                            Import CSV
                        </button>
                        <button type="button" id="btn-export" class="sb-sidebar-link">
                            <x-heroicon-o-arrow-down-tray class="sb-sidebar-link-icon" />
                            Export encrypted
                        </button>
                    </nav>

                    <p class="sb-sidebar-title mt-6">Settings</p>
                    <nav class="sb-sidebar-nav" aria-label="Settings">
                        @if ($isHost)
                            <a href="{{ route('settings.invites') }}" class="sb-sidebar-link">
                                <x-heroicon-o-user-plus class="sb-sidebar-link-icon" />
                                Invite users
                            </a>
                            <a href="{{ route('settings.audit') }}" class="sb-sidebar-link">
                                <x-heroicon-o-clipboard-document-list class="sb-sidebar-link-icon" />
                                Audit log
                            </a>
                        @endif
                        <button type="button" id="btn-change-master" class="sb-sidebar-link">
                            <x-heroicon-o-key class="sb-sidebar-link-icon" />
                            Change master password
                        </button>
                    </nav>
                </div>

                <div class="sb-sidebar-footer">
                    <button type="button" id="btn-lock" class="sb-btn-accent-solid mb-2 w-full">Lock vault</button>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sb-btn-danger-solid w-full">Sign out</button>
                    </form>
                </div>
            </aside>

            <div class="sb-vault-main">
                <div class="sb-vault-main-user hidden lg:flex" aria-label="Signed in user">
                    <x-hashward.user-badge :name="$userName" />
                </div>
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
                            <x-heroicon-o-plus class="sb-btn-icon" />
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
                                <th scope="col" class="sb-table-col-tags">Tags</th>
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
                <x-heroicon-o-bars-3 class="sb-mobile-bar-icon" />
                <span class="sb-mobile-bar-label">Actions</span>
            </button>
            <button type="button" id="btn-mobile-account" class="sb-mobile-bar-btn"
                aria-controls="mobile-account-panel" aria-expanded="false">
                <x-heroicon-o-cog-6-tooth class="sb-mobile-bar-icon" />
                <span class="sb-mobile-bar-label">Settings</span>
            </button>
            <div class="sb-mobile-bar-user">
                <x-hashward.user-badge :name="$userName" />
            </div>
            <button type="button" id="btn-mobile-bar-lock" class="sb-mobile-bar-btn sb-mobile-bar-btn--accent">
                <x-heroicon-o-lock-closed class="sb-mobile-bar-icon" />
                <span class="sb-mobile-bar-label">Lock</span>
            </button>
            <form method="post" action="{{ route('logout') }}" class="sb-mobile-bar-form">
                @csrf
                <button type="submit" class="sb-mobile-bar-btn sb-mobile-bar-btn--danger">
                    <x-heroicon-o-arrow-right-on-rectangle class="sb-mobile-bar-icon" />
                    <span class="sb-mobile-bar-label">Sign out</span>
                </button>
            </form>
        </nav>

        <div id="mobile-actions-panel" class="sb-sheet-panel lg:hidden" role="region" aria-labelledby="mobile-actions-title" hidden>
            <div class="sb-sheet-body">
                <h2 id="mobile-actions-title" class="sb-sheet-title">Actions</h2>
                <nav class="sb-sidebar-nav" aria-label="Actions">
                    <button type="button" id="btn-mobile-generator" class="sb-sidebar-link">
                        <x-heroicon-o-sparkles class="sb-sidebar-link-icon" />
                        Password generator
                    </button>
                    <button type="button" id="btn-mobile-import" class="sb-sidebar-link">
                        <x-heroicon-o-arrow-up-tray class="sb-sidebar-link-icon" />
                        Import CSV
                    </button>
                    <button type="button" id="btn-mobile-export" class="sb-sidebar-link">
                        <x-heroicon-o-arrow-down-tray class="sb-sidebar-link-icon" />
                        Export encrypted
                    </button>
                </nav>
            </div>
        </div>

        <div id="mobile-account-panel" class="sb-sheet-panel lg:hidden" role="region" aria-labelledby="mobile-account-title" hidden>
            <div class="sb-sheet-body">
                <h2 id="mobile-account-title" class="sb-sheet-title">Settings</h2>
                <nav class="sb-sidebar-nav" aria-label="Settings">
                    @if ($isHost)
                        <a href="{{ route('settings.invites') }}" class="sb-sidebar-link">
                            <x-heroicon-o-user-plus class="sb-sidebar-link-icon" />
                            Invite users
                        </a>
                        <a href="{{ route('settings.audit') }}" class="sb-sidebar-link">
                            <x-heroicon-o-clipboard-document-list class="sb-sidebar-link-icon" />
                            Audit log
                        </a>
                    @endif
                    <button type="button" id="btn-mobile-change-master" class="sb-sidebar-link">
                        <x-heroicon-o-key class="sb-sidebar-link-icon" />
                        Change master password
                    </button>
                </nav>
            </div>
        </div>
    </div>

    <dialog id="detail-dialog" class="sb-dialog" aria-labelledby="detail-title">
        <div class="sb-dialog-body sb-detail-dialog-body">
            <div class="sb-detail-dialog-top">
                <button type="button" id="detail-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
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
                    <button type="button" id="detail-toggle-pass" class="sb-password-toggle sb-btn-ghost shrink-0" aria-label="Show password" aria-pressed="false">
                        <x-hashward.password-toggle-icons />
                    </button>
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
            <div class="flex items-start justify-between gap-3">
                <h2 id="entry-dialog-title" class="text-lg font-semibold">Entry</h2>
                <button type="button" id="entry-dialog-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
                </button>
            </div>
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
            <x-hashward.password-field
                id="entry-password"
                label="Password"
                autocomplete="new-password"
            />
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
            <div class="mb-4 flex items-start justify-between gap-3">
                <h2 class="text-lg font-semibold">Password generator</h2>
                <button type="button" id="gen-dialog-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
                </button>
            </div>
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
            <div class="sb-generator-actions sb-generator-actions-primary">
                <button type="button" id="gen-regenerate" class="sb-btn-primary">Regenerate</button>
                <button type="button" id="gen-copy" class="sb-btn-ghost">Copy</button>
            </div>
        </div>
    </dialog>

    <dialog id="change-master-dialog" class="sb-dialog sb-dialog-md" aria-labelledby="change-master-dialog-title">
        <form id="change-master-form" class="sb-dialog-body space-y-4">
            <div class="flex items-start justify-between gap-3">
                <h2 id="change-master-dialog-title" class="text-lg font-semibold">Change master password</h2>
                <button type="button" id="change-master-dialog-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
                </button>
            </div>
            <p class="text-sm text-(--color-sb-muted)">Your new master password re-encrypts every vault item in this browser. It is never sent to the server in plain text.</p>
            <x-hashward.password-field
                id="change-master-current"
                label="Current master password"
                autocomplete="current-password"
                required
            />
            <x-hashward.password-field
                id="change-master-new"
                label="New master password"
                hint="At least 12 characters."
                autocomplete="new-password"
                required
                minlength="12"
            />
            <x-hashward.password-field
                id="change-master-confirm"
                label="Confirm new password"
                autocomplete="new-password"
                required
                minlength="12"
            />
            <div id="change-master-error" class="sb-alert-error hidden" role="alert" hidden></div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" id="change-master-cancel" class="sb-btn-ghost">Cancel</button>
                <button type="submit" id="change-master-submit" class="sb-btn-primary w-auto">Update password</button>
            </div>
        </form>
    </dialog>

    <dialog id="export-dialog" class="sb-dialog sb-dialog-md" aria-labelledby="export-dialog-title">
        <form id="export-form" class="sb-dialog-body space-y-4">
            <div class="flex items-start justify-between gap-3">
                <h2 id="export-dialog-title" class="text-lg font-semibold">Export encrypted backup</h2>
                <button type="button" id="export-dialog-close" class="sb-dialog-close" aria-label="Close">
                    <x-heroicon-o-x-mark class="sb-dialog-close-icon" />
                </button>
            </div>
            <p class="text-sm text-(--color-sb-muted)">Downloads a JSON file encrypted with a passphrase you choose. Store the passphrase separately from the file.</p>
            <x-hashward.password-field
                id="export-passphrase"
                label="Export passphrase"
                hint="At least 12 characters."
                autocomplete="new-password"
                required
                minlength="12"
            />
            <x-hashward.password-field
                id="export-passphrase-confirm"
                label="Confirm passphrase"
                autocomplete="new-password"
                required
                minlength="12"
            />
            <div id="export-error" class="sb-alert-error hidden" role="alert" hidden></div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" id="export-cancel" class="sb-btn-ghost">Cancel</button>
                <button type="submit" id="export-submit" class="sb-btn-primary w-auto">Download export</button>
            </div>
        </form>
    </dialog>

    <input type="file" id="import-file" accept=".csv,text/csv" class="hidden">

    <template id="tpl-icon-star">
        <x-heroicon-s-star class="sb-table-fav-icon" />
    </template>
</div>
@endsection
