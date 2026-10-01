# Production operations

## Release checklist

1. Confirm the release commit and create a verified encrypted backup.
2. Deploy `main` through Hostinger Git deployment.
3. Run migrations with `php artisan migrate --force`.
4. rebuild the configuration, route and view caches.
5. Run `php artisan crm:ops-check` and test `/up`.
6. Smoke-test authentication, core CRUD, permissions and integrations.

Never run destructive demo SQL against production.

## Environment ownership

Production secrets belong in Hostinger environment settings or a private `.env` file. Back up `APP_KEY` separately in a password manager. Losing it can make encrypted database fields and recovery artifacts unreadable.

Required secret classes include database credentials, `APP_KEY`, JWT secret, SMTP password, Telegram token and webhook secrets. Rotate them after staff changes, suspected exposure or migration between providers.

## Scheduler

Laravel's scheduler must run every minute. Confirm the correct PHP binary and application path in Hostinger:

```cron
* * * * * cd /home/USER/domains/DOMAIN/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Check the application logs and scheduler heartbeat after changing the cron configuration.

## Queue strategy

- Shared hosting: `QUEUE_CONNECTION=sync`.
- Managed VPS or process supervisor: `QUEUE_CONNECTION=database` and a continuously supervised `queue:work` process.

After deploying code used by long-running workers, restart them with `php artisan queue:restart`.

## Backups and recovery

Create an encrypted backup and retain 14 copies:

```bash
php artisan crm:backup --retention=14
php artisan crm:backup-verify
```

Keep at least one recent copy outside the hosting account. Test a restore on an isolated database periodically. A backup is not considered usable until verification and a recovery drill succeed.

The historical encrypted migration snapshot and its restore procedure are documented in [database/backups/README.md](../database/backups/README.md).

## Health monitoring

```bash
php artisan crm:ops-check
php artisan crm:sla-check
php artisan crm:deadline-reminders
```

Monitor HTTP availability for `/up`, application error rates, failed jobs, storage usage, backup age, TLS expiry and outbound mail delivery. Alerts should go to an address or channel independent of the monitored server.

## Mail and password recovery

Configure an authenticated SMTP mailbox and verify SPF, DKIM and DMARC for the sending domain. Test delivery to multiple providers and ensure reset messages do not expose whether an account exists.

## Telegram

Configure these values privately:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_WEBHOOK_HEADER_SECRET=
```

Register commands with `php artisan telegram:setup-commands`. Use either a validated HTTPS webhook or the polling command, not both. Access is controlled by the `telegram.use` permission, which a super admin can assign through roles and permissions.

## Incident response

1. Preserve logs and record the affected time window.
2. Disable compromised accounts or integrations.
3. Rotate exposed credentials and invalidate sessions.
4. Restore only from a verified clean backup if integrity is uncertain.
5. Document impact, remediation and preventive actions.

