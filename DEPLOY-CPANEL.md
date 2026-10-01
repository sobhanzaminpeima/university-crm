# Hostinger and cPanel deployment

This guide deploys the Laravel application without exposing private application files or secrets.

## 1. Connect the repository

In Hostinger, select **Import website > Deploy from GitHub** and configure:

- Repository: `sobhanzaminpeima/university-crm`
- Branch: `main`
- Root directory: `public_html`
- PHP version: 8.3

The root `index.php` routes requests into Laravel's `public` directory. Keep automatic deployment enabled only for the protected production branch.

For a conventional cPanel installation, place the project outside the public web root and point the domain document root at the project's `public` directory. If the provider cannot change the document root, use the repository's root entry point and `.htaccess` exactly as supplied.

## 2. Create the database

Create a MySQL database and a dedicated user with access only to that database. Use a unique password and keep it in Hostinger's environment settings, never in Git.

For a fresh installation, run Laravel migrations and the approved production seeder. `database/schema-and-seed.sql` is a destructive legacy/demo installer and must not be imported over a live database.

To restore the supplied encrypted production snapshot, follow [database/backups/README.md](database/backups/README.md).

## 3. Configure environment variables

Import `.env.example` as a template and replace every placeholder. At minimum configure:

```env
APP_NAME="Virtue Visa CRM"
APP_ENV=production
APP_KEY=base64:GENERATED_SECRET
APP_DEBUG=false
APP_URL=https://virtuevisa.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_DATABASE
DB_USERNAME=YOUR_DATABASE_USER
DB_PASSWORD=YOUR_UNIQUE_PASSWORD

QUEUE_CONNECTION=sync
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.virtuevisa.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=YOUR_MAILBOX
MAIL_PASSWORD=YOUR_MAILBOX_PASSWORD
MAIL_FROM_ADDRESS=noreply@virtuevisa.com
```

Generate `APP_KEY` once with `php artisan key:generate`. Never replace it on an existing installation: encrypted integration credentials, cookies and backups depend on it.

Optional Telegram values are documented in [docs/PRODUCTION_OPERATIONS.md](docs/PRODUCTION_OPERATIONS.md).

## 4. Complete the release

From Hostinger's terminal, in `public_html`, run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan crm:ops-check
```

The repository has no Composer lifecycle scripts because Hostinger shared hosting may disable `proc_open`. Hostinger may still run its own `composer install` safely during deployment.

Ensure `storage` and `bootstrap/cache` are writable by the PHP process. Do not use world-writable permissions.

## 5. Configure scheduled work

Create a cron job that runs every minute:

```cron
* * * * * cd /home/USER/domains/DOMAIN/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the exact PHP binary shown by Hostinger. The scheduler handles operational checks, reminders and scheduled backup work registered by the application.

On shared hosting keep `QUEUE_CONNECTION=sync`. If a persistent worker is available, use the database queue and supervise:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## 6. DNS and Cloudflare

- Point the apex and `www` records at the new Hostinger origin.
- Use **Full (strict)** SSL mode with a valid origin certificate.
- Purge cached HTML after switching origins.
- Exclude login, authenticated CRM pages, webhook endpoints and `/up` from full-page caching.
- Verify both the Hostinger origin and public domain return the new release.

If the origin `/up` returns 200 but the public `/up` returns 404, Cloudflare or DNS is still serving the old origin.

## 7. Production verification

- `/up` returns HTTP 200.
- Login, logout and CSRF protection work over HTTPS.
- Password reset email is received through the configured SMTP account.
- Super admin can assign `telegram.use`; an unauthorized role cannot access Telegram features.
- Tenant users cannot view or modify another tenant's records.
- Student, application and university create/update workflows succeed.
- `php artisan crm:ops-check` reports database, private storage and backup status as healthy.
- `php artisan crm:backup --retention=14` and `php artisan crm:backup-verify` succeed.
- Cron produces a recent scheduler heartbeat.

There are no supported public default credentials. Create accounts securely, enforce strong passwords and rotate any credentials inherited from an older host.

## 8. Rollback

Before every release, create and verify an encrypted backup. To roll back application code, redeploy the previous known-good commit. Do not reverse a database migration until its data impact is understood. Restore a database only from a verified backup and follow the recovery procedure in the backup guide.
