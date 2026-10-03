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
