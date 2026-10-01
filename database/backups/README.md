# Production database transfer

`virtuevisa-production-2026-10-01.sql.enc` is the encrypted database snapshot supplied for the Hostinger migration. The plaintext dump contains personal data and password hashes and must never be committed or placed under a public web root.

The decryption key is intentionally not stored in Git. Keep it outside the deployed repository.

## Restore on Hostinger with SSH

Upload the key to a private location outside `public_html`, then run:

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
  -in database/backups/virtuevisa-production-2026-10-01.sql.enc \
  -out /tmp/virtuevisa-production.sql \
  -pass file:/home/USERNAME/private/hostinger-db-backup.key

mysql -h DB_HOST -u DB_USERNAME -p DB_DATABASE < /tmp/virtuevisa-production.sql
php artisan migrate --force
rm /tmp/virtuevisa-production.sql
php artisan optimize:clear
php artisan config:cache
```

Replace `USERNAME`, `DB_HOST`, `DB_USERNAME`, and `DB_DATABASE` with the Hostinger values. Do not put the database password in shell history; enter it at the MySQL prompt.

## Restore without SSH

Decrypt the file on a trusted computer using the same `openssl enc -d` command, import the resulting SQL using Hostinger phpMyAdmin, and immediately delete the plaintext SQL. Run pending Laravel migrations from Hostinger's terminal after import.

The snapshot migration history ends at `2026_08_18_000500_create_visa_cases`. The application also requires `2026_10_01_000100_add_telegram_use_permission`, which is applied by `php artisan migrate --force`.
