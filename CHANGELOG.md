# Changelog

Notable changes to the Hashward password vault app (October 2026 cycle).

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased]

### Added

- **Host & invites** — Single host; invite-only users; host `settings/invites` (create, view link with token rotation, revoke, delete); guest accept flow; `resources/js/invites.js`; invite audit events.
- **Audit log** — Paginated listing (10 per page, scoped to signed-in user); `pagination/hashward` with chevron prev/next; compact single-line pager on mobile.
- **Vault modals** — Change master password and export encrypted backup (inline validation, no browser prompts).
- **Password visibility** — `x-hashward.password-field` and `password-toggle.js` (eye icons) on unlock, entry, setup, login, invite accept, modals, and detail password.
- **Export backups** — Self-describing JSON (`format: passphrase-1`, documented KDF); legacy envelopes still importable.
- **Hashward branding** — Rename from Strongbox; logo, favicon, shared `components/hashward/` views.

### Changed

- **Vault UI** — Sidebar **Settings** with Heroicons; user initial above main bar (desktop); entry table with border, row lines, and mobile cards; bottom bar and sheets under 1024px; responsive vault toolbar; detail dialog close in header only.
- **Invites & audit settings** — Invite cards and responsive create form on small screens; audit header with inline back link; mobile-friendly audit table (two-line timestamps, fixed layout).

### Fixed

- Logo asset alpha channel; mobile bar showing on desktop (`lg`); invites CSP (bundled JS); invites/audit horizontal overflow on narrow screens.

### Tests

- `HostUserTest`, `UserInviteTest`, `AuditLogPageTest`; crypto export tests in `tests/Unit/crypto.test.js`.

---

## 2026-10-01 — Vault UI & API hardening

### Added

- Mobile vault layout (cards under 640px, safe-area padding).
- `BulkStoreVaultItemsRequest`, `AuditExportRequest`, and audit export endpoint.
- `formatApiErrorMessage` (`vault.js`) and `formBackedFetchMethod` (`http.js`).
- Feature tests for 2FA session gate, rate limits, CSRF, and `_method=PUT` updates; `SECURITY_CHECKLIST.md`.

### Changed

- Table-style vault list with detail modal; centered `dialog.sb-dialog` modals.
- Sidebar **Actions** / account links; 30-minute idle auto-lock; dark-only UI.
- Generator and top-bar layout fixes for full-width button/input CSS.

### Fixed

- Entry save via POST + `_method=PUT` (multipart on PUT).
- Vault CSRF / 419 handling; login redirect loop for authenticated users.

---

## Earlier foundation

- Laravel 12: login, TOTP 2FA, vault setup, encrypted `vault_items`, audit log page.
- Client crypto (PBKDF2 + AES-GCM), vault policies, security headers.
- No public registration; `php artisan vault:create-user`.

Session-authenticated `/api` vault routes (CSRF, `two_factor`, throttle) — ciphertext only; master password never sent to server.

---

## Verify locally

1. `composer install` && `npm install`
2. `php artisan test` && `npm run test:unit`
3. `npm run dev` (or `npm run build`)
4. `php artisan serve` — sign in, 2FA, unlock vault, edit an entry.
5. Narrow viewport: bottom **Actions** / **Settings** bar; desktop (≥1024px): sidebar only.
