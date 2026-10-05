# cPanel deployment

1. Upload the project **outside** `public_html` (e.g. `~/password-vault`).
2. Point the domain document root to `password-vault/public` (cPanel → Domains → Document Root).
3. Create a MySQL database and user with privileges on that database only. Disable Remote MySQL.
4. Copy `.env.example` to `.env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your.domain`, database credentials, and `HASH_DRIVER=argon2id`.
5. Run on the server (SSH or cPanel Terminal):

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan vault:create-user
```

6. Permissions: directories `750`, files `640`, `.env` `600`.
7. Enable AutoSSL / HTTPS; uncomment the HTTPS rewrite rules in `public/.htaccess`.
8. Cron (daily backup example):

```cron
0 3 * * * cd /home/you/password-vault && php artisan vault:backup >> /home/you/logs/vault-backup.log 2>&1
```

Backups are written to `storage/backups/` (outside the web root).

## Troubleshooting

### HTTP 405 (Method Not Allowed)

1. **Document root** must be `password-vault/public`, not the project root or `public_html` with the whole repo inside it. If only GET works but sign-in or vault actions return 405, this is the most common cause.
2. **Rebuild front-end assets** after deploy (`npm ci && npm run build`) so the vault uses POST + `_method` for JSON updates/deletes on hosts that block PUT/DELETE.
3. **Apache rewrite**: `public/.htaccess` must be honored (`AllowOverride All`, `mod_rewrite` on). Requests should reach `public/index.php`.
4. **Route cache**: after changing routes, run `php artisan route:clear` then `php artisan route:cache`, or skip route caching until deploy succeeds.
5. In the browser **Network** tab, note the failing URL and method (e.g. `POST /login`, `POST /api/crypto/rotate`). A 405 on `POST /login` almost always means the web server is not routing to Laravel.
6. **Symfony-style “405 Method Not Allowed” page** after sign-in usually means `POST /vault` or `POST /vault/setup` — the unlock/setup forms fell back to a normal browser submit because JavaScript did not run (missing `public/build`, blocked script, or stale assets). Run `npm ci && npm run build`, hard-refresh, and confirm `public/build/manifest.json` exists on the server.
