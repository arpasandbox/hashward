# Security checklist

Run these after each production deploy and periodically (e.g. monthly).

## Automated tests

- [ ] `php artisan test` (auth, 2FA, IDOR, CSRF, rate limits, security headers)

## Dependencies

- [ ] `composer audit`
- [ ] `npm audit` (development/build machine only)

## Automated scans

- [ ] [OWASP ZAP](https://www.zaproxy.org/) baseline scan against staging/production URL
- [ ] [Mozilla Observatory](https://observatory.mozilla.org/)
- [ ] [securityheaders.com](https://securityheaders.com/)
- [ ] [SSL Labs](https://www.ssllabs.com/ssltest/) (HTTPS / TLS)

## Application (OWASP ASVS Level 1 spot checks)

- [ ] Registration and password reset routes return 404
- [ ] Login rate limiting and account lockout after repeated failures
- [ ] TOTP required before vault access
- [ ] Vault API returns only ciphertext for the authenticated user
- [ ] CSRF enforced on state-changing requests (Laravel skips CSRF during `php artisan test`; use browser Network tab or staging for 419 checks)
- [ ] Security headers present (CSP, HSTS, X-Frame-Options, nosniff)
- [ ] `APP_DEBUG=false` in production; generic error pages
- [ ] Logs contain no request bodies, passwords, TOTP codes, or ciphertext
- [ ] Document root is `/public`; `.env` not web-accessible
- [ ] Cron backups stored outside web root; old backups pruned

## Manual crypto sanity

- [ ] Master password never appears in Network tab request payloads
- [ ] Database rows for `vault_items.ciphertext` are not human-readable JSON credentials
- [ ] Wrong master password fails unlock without server round-trip
