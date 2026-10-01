# Virtue Visa CRM

Production multi-tenant CRM and student portal for international education and visa agencies.

## Technology

- Laravel 12.69+
- PHP 8.3+
- MySQL 8 / MariaDB 10.6+
- Blade, Bootstrap and vanilla JavaScript
- Database-backed roles and permissions

The Laravel application in this repository is the canonical product. It includes the administrator CRM, student portal, Telegram integration, operational commands and production deployment files.

## Main capabilities

- Tenant-isolated students, leads, applications, universities and visa cases
- Super admin, admin, agent, sub-agent and student roles
- Configurable permissions, including `telegram.use`
- Student requests, notes, tasks, document handling and audit history
- Application pipeline, enrollment probability and next-action guidance
- Password recovery, session security and login throttling
- Telegram bot commands and webhook/polling support
- SLA checks, deadline reminders, queue storage and scheduler support
- Encrypted database backups with integrity verification
- English, Persian and Turkish localization

## Requirements

- PHP 8.3 or newer with the extensions required by Laravel
- Composer 2
- MySQL or MariaDB
- HTTPS in production
- A cron scheduler; a queue worker is optional

## Local installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Set the database and mail values in `.env` before running the migrations. The repository does not publish a production password or a reusable default administrator password. Provision the first privileged account securely and rotate any imported credentials before launch.

## Hostinger production deployment

The current production layout uses:

- Repository: `sobhanzaminpeima/university-crm`
- Branch: `main`
- Web root: `public_html`
- PHP: 8.3

Hostinger installs Composer dependencies during Git deployment. Composer lifecycle scripts are intentionally empty because restricted shared-hosting builds may disable `proc_open`.

After the first deployment, or after a release containing migrations, run from the application directory:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan crm:ops-check
```

Do not commit `.env`, database passwords, Telegram tokens, SMTP credentials, `APP_KEY`, or backup encryption keys. See [DEPLOY-CPANEL.md](DEPLOY-CPANEL.md) for the full Hostinger/cPanel procedure.

## Scheduler and queue

Run Laravel's scheduler every minute:

```cron
* * * * * cd /home/USER/domains/DOMAIN/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

`QUEUE_CONNECTION=sync` is safe on shared hosting without a persistent process. Environments with a supervised worker may use `database` and run `php artisan queue:work`.

## Operations

```bash
php artisan crm:ops-check
php artisan crm:sla-check
php artisan crm:deadline-reminders
php artisan crm:backup --retention=14
php artisan crm:backup-verify
```

Backups are encrypted and stored outside the public disk. Keep `APP_KEY` and the backup key available in a separate secure recovery location; encrypted data cannot be recovered without the correct keys.

## Quality checks

```bash
php scripts/quality-check.php
composer audit --locked
```

Before a production release, also verify login/logout, password recovery delivery, role permissions, tenant isolation, student and application CRUD, Telegram authorization, scheduler execution, backup verification and the `/up` health endpoint.

## Documentation

- [Production deployment](DEPLOY-CPANEL.md)
- [Production operations](docs/PRODUCTION_OPERATIONS.md)
- [Security guide](docs/SECURITY.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Testing and QA](docs/TESTING_AND_QA.md)
- [Database recovery](database/backups/README.md)
- [Change log](docs/CHANGELOG.md)

## License

Proprietary. Redistribution or resale requires written permission from the owner.
