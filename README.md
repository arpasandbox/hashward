<div align="center">

# 🔐 Hashward

**A self-hosted, zero-knowledge password vault with two-factor sign-in and invite-only access.**

Your master password never leaves your browser. The server only ever sees ciphertext.

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-frontend-646CFF?logo=vite&logoColor=white)
![Tests](https://img.shields.io/badge/tests-PHPUnit%20%2B%20Vitest-brightgreen)
![Status](https://img.shields.io/badge/status-in%20development-orange)

</div>

---

## Why Hashward?

Most password managers ask you to trust a company with your secrets. Hashward is built so you don't have to trust the server at all, including your own.

- **Zero-knowledge by design.** Vault entries are encrypted in the browser (PBKDF2 key derivation + AES-GCM) before they are sent anywhere. The database stores only ciphertext.
- **Two separate secrets.** Your *account login* (email, password, authenticator code) gets you in the door. Your *master password* unlocks the vault and is never transmitted.
- **Small, private, and yours.** One host, invite-only users, no public sign-up. Deploys to ordinary shared hosting (cPanel) as easily as to a VPS.
- **Honest about recovery.** Forget your master password and nobody can recover it for you. That is the point.

> Hashward was previously developed under the name **Strongbox**.

## Features

| | |
|---|---|
| 🔑 **Encrypted vault** | Entries (username, password, website, notes, tags) are encrypted client-side before storage |
| 🛡️ **Two-factor sign-in** | Account login plus TOTP authenticator code; vault access is blocked until 2FA is complete |
| ⏱️ **Auto-lock** | The vault locks itself after a period of inactivity |
| 🎲 **Password generator** | Built-in generator in the entry editor |
| 💾 **Encrypted backups** | Export a self-describing, passphrase-protected JSON backup (`format: passphrase-1`); older envelopes remain importable |
| 🔄 **Master password change** | Change the master password from inline modals, with validation and no browser prompts |
| 📨 **Invite-only access** | A single host creates, views, rotates, and revokes invite links. There is no public registration |
| 📜 **Audit log** | Paginated, per-user log of security-relevant events, including invite activity, with export |
| 📱 **Responsive UI** | Table view on desktop, card layout and bottom action bar on mobile; dark theme |
| 👁️ **Password visibility toggles** | Show/hide controls across unlock, entry, setup, login, and invite screens |

## How it works

```
┌────────────────────────┐                      ┌──────────────────────────┐
│        Browser         │                      │          Server          │
│                        │                      │         (Laravel)        │
│  master password       │                      │                          │
│       │ PBKDF2         │                      │  auth + TOTP 2FA         │
│       ▼                │    ciphertext only   │  rate limits + lockout   │
│  vault key ──AES-GCM──▶│ ───────────────────▶ │  vault_items.ciphertext  │
│                        │                      │  audit log               │
│  plaintext exists      │ ◀─────────────────── │                          │
│  only here             │    ciphertext only   │  never sees master pw    │
└────────────────────────┘                      └──────────────────────────┘
```

1. **Sign in** with your account email, account password, and authenticator code.
2. **Unlock** the vault by entering your master password. A key is derived in the browser; a wrong password fails locally, with no server round-trip.
3. **Read and write** entries. The `/api` vault routes are session-authenticated, CSRF-protected, rate-limited, gated on 2FA, and return only ciphertext for the authenticated user.

## Tech stack

- **Backend:** PHP 8.2+, Laravel 12, [`pragmarx/google2fa`](https://github.com/antonioribeiro/google2fa) for TOTP
- **Password hashing:** Argon2id (`HASH_DRIVER=argon2id`)
- **Frontend:** Vite, Blade components, Heroicons (`blade-ui-kit/blade-heroicons`)
- **Client crypto:** PBKDF2 + AES-GCM
- **Database:** MySQL (production)
- **Testing:** PHPUnit 11 (feature and unit) and Vitest (client crypto)

## Getting started

### Requirements

- PHP 8.2+ and [Composer](https://getcomposer.org/)
- Node.js and npm
- A database (MySQL for production)

### Install

```bash
git clone https://github.com/arpasandbox/hashward.git
cd hashward

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials, then:

```bash
php artisan migrate
npm run build        # or `npm run dev` while developing
```

Composer also provides a one-shot shortcut that does the steps above:

```bash
composer setup
```

### Create your first user

There is no public registration. Users are created from the command line:

```bash
php artisan vault:create-user
```

Once signed in as the host, you can invite others from **Settings → Invites**.

### Run it

```bash
php artisan serve
```

Sign in, complete 2FA, set up your master password, and unlock the vault.

For a full dev environment (server, queue, logs, and Vite together):

```bash
composer dev
```

## Configuration

Copy `.env.example` to `.env` and set at least:

| Variable | Recommended value | Notes |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Never enable in production |
| `APP_URL` | `https://your.domain` | |
| `DB_*` | your credentials | Use a database user scoped to this database only |
| `HASH_DRIVER` | `argon2id` | Account password hashing |

## Deployment

Hashward is documented for **cPanel / shared hosting**. See **[deploy.md](deploy.md)** for the full walkthrough. The essentials:

- Upload the project **outside** `public_html` and point the domain's document root at the project's `public/` directory.
- Use a MySQL user with privileges on a single database, with Remote MySQL disabled.
- Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, cache config/routes/views, and `npm ci && npm run build`.
- Set permissions to `750` for directories, `640` for files, and `600` for `.env`.
- Enable HTTPS and uncomment the HTTPS rewrite rules in `public/.htaccess`.
- Schedule backups with cron:

  ```
  0 3 * * * cd /home/you/password-vault && php artisan vault:backup >> /home/you/logs/vault-backup.log 2>&1
  ```

  Backups are written to `storage/backups/`, outside the web root.

Getting a **405 Method Not Allowed**? It's almost always the document root or missing built assets. The troubleshooting section in [deploy.md](deploy.md) covers it.

## Security

### What Hashward protects against

- **Database or server-side data exposure.** Vault rows hold ciphertext, and the master password and derived key never reach the server.
- **Credential stuffing and brute force.** Sign-in is rate-limited with account lockout after repeated failures.
- **Account takeover without the second factor.** TOTP is required before any vault access.
- **Cross-user data access.** Vault routes are scoped to the authenticated user and covered by IDOR tests.
- **Common web attacks.** CSRF enforcement, a strict Content Security Policy, HSTS, `X-Frame-Options`, and `nosniff`.
- **Log leakage.** Logs contain no request bodies, passwords, TOTP codes, or ciphertext.

### What it can't protect against

Being upfront about the limits of any web-based zero-knowledge design:

- **A compromised device or browser.** Malware or a hostile extension can read secrets once the vault is unlocked.
- **A weak master password.** Key derivation slows guessing but can't rescue a guessable password.
- **A compromised web server serving malicious JavaScript.** Because the crypto runs in code delivered by the server, an attacker who controls the server could ship altered code. Keep your host patched and monitor it.
- **A lost master password.** There is no recovery path by design. Keep an encrypted backup.

### Security checklist

[SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md) lists the checks to run after each deploy and monthly: automated tests, `composer audit` / `npm audit`, OWASP ZAP baseline scans, Mozilla Observatory, SSL Labs, ASVS Level 1 spot checks, and manual crypto sanity checks such as confirming the master password never appears in the Network tab.

### Reporting a vulnerability

Please **don't** open a public issue for security problems. Contact the maintainer privately via the email or security-advisory option on the GitHub profile instead.

## Testing

```bash
php artisan test        # PHPUnit: auth, 2FA gate, IDOR, CSRF, rate limits, headers, invites, audit log
npm run test:unit       # Vitest: client-side crypto and export
```

## Project structure

```
app/          Laravel application code (controllers, requests, policies)
bootstrap/    Framework bootstrap
config/       Application configuration
database/     Migrations
public/       Web root (point your document root here)
resources/    Blade views, JS (vault, invites, password toggles), styles
routes/       Web and API routes
storage/      Logs, cache, and backups
tests/        PHPUnit feature/unit tests and Vitest crypto tests
```

## Documentation

- [Design notes](Hashward_%20password%20manager%20design.md): UI copy and flow for sign-in, unlock, and the entry view
- [Deployment guide](deploy.md)
- [Security checklist](SECURITY_CHECKLIST.md)
- [Changelog](CHANGELOG.md)

## Roadmap

Ideas to consider as the project matures (edit to match your plans):

- [ ] Independent security review of the client crypto
- [ ] Browser extension for autofill
- [ ] Shared vaults or folders between invited users
- [ ] Light theme
- [ ] Docker setup for local development

## Contributing

Issues and pull requests are welcome. Before opening a PR, please run both test suites (`php artisan test` and `npm run test:unit`) and format PHP with `./vendor/bin/pint`.

## License

Add a `LICENSE` file and state it here. (The Laravel skeleton is MIT-licensed; your project's license is your choice.)

---

<div align="center">
Built with Laravel · Encrypted in your browser · Yours to host
</div>
