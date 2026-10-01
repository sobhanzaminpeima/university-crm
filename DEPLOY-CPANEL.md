# cPanel Deployment Guide (No SSH)

## 1) Prepare Package
Upload the whole `laravel-crm-cpanel` project to your hosting account as:
- `/home/USERNAME/vertue-crm/` (private app root)

Then place web entry files in:
- `/home/USERNAME/public_html/`

Copy these from project:
- `public_html/index.php` -> `/public_html/index.php`
- `public_html/.htaccess` -> `/public_html/.htaccess`
- `public/assets/*` -> `/public_html/assets/*`

## 2) Path Check in `public_html/index.php`
Default assumes app root is one level up:
```php
require __DIR__.'/../vertue-crm/vendor/autoload.php';
$app = require_once __DIR__.'/../vertue-crm/bootstrap/app.php';
```
Adjust folder name if yours is different.

## 3) Create Database in cPanel
1. Open **MySQL Databases**.
2. Create DB (example): `cpaneluser_vertue_crm`
3. Create DB user + password.
4. Assign user to DB with **ALL PRIVILEGES**.

## 4) Import SQL
1. Open **phpMyAdmin**.
2. Select DB.
3. Import file:
   - `database/schema-and-seed.sql`

For the production migration that preserves the existing `virtuevisa.com` data, use the encrypted snapshot and restore instructions in `database/backups/README.md` instead of `database/schema-and-seed.sql`.

## 5) Configure `.env`
In `/home/USERNAME/vertue-crm/.env`:
```env
APP_NAME="Vertue CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_vertue_crm
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=your_db_password

JWT_SECRET=replace_with_long_random_secret
JWT_TTL_MINUTES=120
SESSION_SECURE_COOKIE=true
```

## 6) Required Writable Paths
In cPanel File Manager, set writable permissions (usually 755/775 depending host policy):
- `storage/`
- `storage/framework/`
- `storage/logs/`
- `bootstrap/cache/`

## 7) First Login
- URL: `https://your-domain.com/login`
- Email: `admincrm@vertue.com`
- Password: `Vertue2026`

## 8) Zero-error Checklist
- Home `/dashboard` opens without 500 error
- Students list loads
- Add/Edit/Delete student works
- Add/Edit/Delete university works
- Add/Edit/Delete application works
- Student Requests Approve/Reject works
- Logout and login cycle works

## 9) Recommended Production Hardening
- Change admin password immediately
- Rotate `JWT_SECRET`
- Set strong DB password
- Enable SSL and force HTTPS
- Enable daily DB backups in cPanel
