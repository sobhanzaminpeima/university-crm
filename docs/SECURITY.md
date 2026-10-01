# Security guide

## Implemented controls

- Password hashing and Laravel authentication protections
- Login throttling and password-reset token storage
- CSRF protection and secure session-cookie settings
- HTTPS security headers and HSTS in production
- Tenant-scoped data access and role/permission authorization
- Dedicated `telegram.use` permission for Telegram features
- Encryption of stored integration secrets
- Encrypted database backups with integrity verification
- Audit records for important CRM actions

## Production requirements

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Serve only over HTTPS and use `SESSION_SECURE_COOKIE=true`.
- Keep `.env`, logs, backups and application internals outside public access.
- Use a unique database account limited to the CRM database.
- Do not reuse passwords or commit tokens, private keys or dumps.
- Protect super-admin accounts with the strongest available controls and the minimum necessary membership.
- Review role permissions after each feature release.
- Patch PHP, Laravel and Composer dependencies on a maintained schedule.

## Secrets and credential rotation

Treat any secret published in chat, tickets, screenshots or repository history as exposed. Replace it in the upstream service, update the private environment, clear the configuration cache and validate the integration.

Do not rotate `APP_KEY` casually. Plan a controlled re-encryption procedure first because encrypted database fields depend on the existing key.

## Authorization verification

Test authorization using at least super admin, admin, agent and student accounts. Verify denied actions on both UI and direct HTTP requests. Tenant isolation must be enforced in database queries, not only by hiding navigation links.

## File and webhook safety

- Validate upload type, size and ownership; serve private documents through authorized application routes.
- Reject executable uploads and randomize stored filenames.
- Validate Telegram webhook path/header secrets and apply rate limits.
- Never log access tokens, passwords or full sensitive payloads.

## Reporting a vulnerability

Report vulnerabilities privately to the repository owner. Include affected version, reproducible steps and impact. Do not open a public issue containing exploit details or customer data.

