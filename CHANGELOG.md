# Changelog

All notable changes to the Hashward password vault app documented for this development cycle (October 2026).

The format is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased] — 2026-10-01 through 2026-10-03

### Added

- **Encrypted export metadata** — Export files include `format: passphrase-1` and `kdf` (`pbkdf2-sha256-aes-gcm-256`) for self-describing backups; decrypt still accepts legacy `hashward-export` and old `v`-only envelopes. Download name is `{timestamp}.json`.
- **Hashward branding** — Product renamed from Strongbox; Blade components under `resources/views/components/hashward/`; logo at `public/images/hashward-logo.png` (transparent PNG); tab favicon from `public/images/favicon.png`.
- **Mobile vault navigation** — Fixed bottom bar with **Actions** and **Account** icons; non-modal slide-up panels above the bar (bar stays tappable); mobile-only action buttons wired directly in `vault.js` (not hidden sidebar proxies).
- **Detail modal** — Circular **×** close control in its own row above the entry title.
- **Crypto tests** — Export round-trip, legacy format ids, and unknown-format rejection in `tests/Unit/crypto.test.js`.

### Changed

- **Logo in UI** — Login, unlock, setup, audit, vault sidebar, and mobile header use the Hashward logo image (sized via `.sb-brand-logo`).
- **Vault toolbar (responsive)** — Below 640px: nav row, then full-width search, then full-width **Add new** (+ icon). Tablet (640px–1023px): search and **Add new** on one row with matched `h-11` height; 20px margin below the toolbar on small viewports.
- **Desktop vs mobile chrome** — Sidebar visible from `lg` up; mobile header is logo-only; bottom bar and sheets only below `1024px` (component CSS scoped to max-width media queries so `lg:hidden` is not overridden).
- **Detail dialog** — Bottom “Close” removed; spacing below close row (`mb-5`).

### Fixed

- **Logo asset** — Prior “PNG” was JPEG data without alpha; replaced with a real RGBA logo so the vault background shows through.
- **Mobile bar on desktop** — `.sb-mobile-bar { display: grid }` was winning over Tailwind `lg:hidden`; mobile chrome is now hidden from 1024px up.

---

## 2026-10-01 (initial UI pass)

### Added

- **Mobile layout (320px+)** — Off-canvas sidebar with Menu toggle and backdrop; vault entry list becomes labeled cards below 640px; safe-area padding; tighter header/dialog spacing on small screens.

### Fixed

- **Edit entry save** — Updates sent encrypted data with `PUT` + `FormData`; PHP does not parse multipart bodies on `PUT`, so Laravel saw no `ciphertext` / `iv`. Saves now use `POST` with `_method=PUT` (Laravel method spoofing), matching create flow reliability.
- **Vault save / 419 errors** — Shared CSRF handling in `resources/js/http.js`; mutating vault requests use `FormData` + `_token` where appropriate; API header merge order fixed so CSRF is not dropped.
- **Login redirect loop** — Authenticated users hitting `/login` or `/` no longer bounce in an endless redirect; guests and signed-in users route to 2FA challenge consistently (`bootstrap/app.php`).
- **Password generator modal** — Length label and input sat on broken rows because `.sb-input` / `.sb-btn-primary` use `w-full`; layout uses a dedicated length row and scoped widths.
- **Header search + New entry** — Same `w-full` issue stacked search and button vertically; top bar uses `flex-nowrap` and scoped widths so they stay on one line.

### Changed

- **Vault idle auto-lock** — Default idle lock is **30 minutes** (`AUTO_LOCK_MINUTES` in `resources/js/vault.js`). Spec updated to match; header countdown and theme toggle removed (dark-only UI).
- **Vault list UI** — Entry list is table-style (Name, Username, Website, Updated); row opens a centered detail modal instead of inline pane.
- **Entry form** — Folder field removed from UI; client still sends `folder: ''` for compatibility.
- **Modals** — Entry, detail, and generator dialogs centered via `dialog.sb-dialog` (`inset: 0`, `margin: auto`).
- **Top bar** — Brand left; **Search vault…** and **New entry** grouped on the right.
- **Sidebar** — **Actions** (generator, import, export) and **Account** (change master password, lock vault, audit log, sign out) sections; sidebar scrolls when content is tall (`overflow-y-auto`, sticky on large screens).
- **Sign out** — Uses `sb-btn-danger` (red) in the Account section.
- **Generator actions** — Order: Regenerate → Copy → Close; Regenerate and Copy grouped with tight spacing; Close aligned to the opposite side.

### Added

- **Audit / quality**
  - `BulkStoreVaultItemsRequest` for bulk import validation.
  - `AuditExportRequest` and `AuditLogController::export` (replaces inline route closure).
  - Feature tests: vault JSON requires `auth.two_factor_passed`; login and vault API rate limits (`VaultRateLimitTest`); update via form `_method=PUT` (`VaultItemsApiTest`); browser-style POST with CSRF header.
  - `SECURITY_CHECKLIST.md` — `php artisan test` step; note that Laravel skips CSRF verification during PHPUnit.
  - Production asset rebuild (`npm run build`) so `public/build` matches current `vault.js` / `http.js`.
- **`formatApiErrorMessage`** in `vault.js` — Surfaces Laravel validation messages in the entry save error banner.
- **`formBackedFetchMethod`** in `http.js` — Centralizes POST + `_method` spoofing for form-backed `PUT` / `PATCH` / `DELETE`.

### Security & architecture (unchanged intent, clarified)

- Vault JSON routes live under `/api` in `routes/web.php` (session, CSRF, `auth`, `two_factor`, `throttle:vault`) — not a public REST API; ciphertext-only payloads.
- Master password and derived keys remain client-side only (zero-knowledge design per spec).

### Tests

- **17+ passing** feature tests after the 2026-10-01 cycle (auth, 2FA, IDOR, headers, rate limits, CSRF client contract, form-spoofed update); **9** crypto unit tests including export envelope coverage (run `npm run test:unit`).

### Known limitations

- PHPUnit runs with CSRF middleware bypassed by Laravel; use staging/browser Network tab for real 419 checks.
- Account lockout beyond route throttle is manual checklist item (see `SECURITY_CHECKLIST.md`).

---

## Earlier foundation (pre–2026-10-01 UI pass)

Included for context if you are reading this as a single “everything so far” log:

- Laravel 12 app: login, TOTP 2FA, vault setup, encrypted `vault_items`, audit log page.
- Client crypto: PBKDF2 + AES-GCM in `resources/js/crypto.js`.
- Policies / IDOR protection on vault items.
- Security headers middleware.
- No public registration; user created via `php artisan vault:create-user`.

---

## How to verify after pulling these changes

1. `composer install` && `npm install`
2. `php artisan test`
3. `npm run dev` (or `npm run build` for production assets)
4. `php artisan serve` — sign in, complete 2FA, unlock vault, create/edit entry, open password generator.
5. On a narrow viewport: bottom **Actions** / **Account** bar, slide-up panels, and responsive search / **Add new** toolbar; on desktop (`≥1024px`): left sidebar only, no bottom bar.
