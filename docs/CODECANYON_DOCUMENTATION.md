# Vertue CRM + Student Portal
## Complete Installation & User Guide (CodeCanyon Edition)

Version: 1.0.0  
Author: Vertue  
Framework: Laravel 12 / PHP 8.3+

## 1) Product Overview
Vertue CRM is a multi-tenant student recruitment CRM with a dedicated Student Portal.

Included systems:
- CRM Web App for Super Admin / Admin / Agent / Sub-Agent
- Student Portal Web App (`/portal/*`)
- API endpoints for portal/mobile integrations (`/api/portal/*`)

Core modules:
- Students
- Universities
- Applications
- Student Requests
- Tasks
- Notifications
- Scholarships
- Finance
- Portal Documents
- Portal Messaging

## 2) Server Requirements
- PHP 8.3 or newer
- MySQL 8+ (or MariaDB compatible)
- Composer 2+
- Required PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `ctype`, `json`, `fileinfo`
- Apache/Nginx with URL rewrite

## 3) Package Structure
- `app/` application code
- `config/` Laravel config files
- `database/schema-and-seed.sql` full schema + demo data
- `routes/` web and API routes
- `resources/views/` Blade templates
- `public/` web root
- `DEPLOY-CPANEL.md` cPanel deployment quick guide

## 4) Quick Installation (Local / XAMPP)
1. Copy project to `htdocs` (example: `C:\xampp\htdocs\laravel-crm-cpanel`).
2. Create a MySQL database (example: `vertue_crm`).
3. Run `php artisan migrate --seed` on a clean local database. The legacy SQL installer is for isolated demo use only.
4. Copy `.env.example` to `.env`.
5. Update `.env`:
   - `APP_ENV=local`
   - `APP_DEBUG=true`
   - `APP_URL=http://127.0.0.1:8000`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=your_database_name`
   - `DB_USERNAME=your_db_user`
   - `DB_PASSWORD=your_db_password`
   - `SESSION_SECURE_COOKIE=false` (for local HTTP)
6. Install dependencies:
   - `composer install`
7. Generate app key:
   - `php artisan key:generate`
8. Create storage symlink:
   - `php artisan storage:link`
9. Clear caches:
   - `php artisan optimize:clear`
10. Run:
   - `php artisan serve`

Open:
- CRM Login: `http://127.0.0.1:8000/login`
- Student Portal Login: `http://127.0.0.1:8000/portal/login`

## 5) Demo Credentials
No reusable credentials are published with the production source. Create test accounts locally with strong, unique passwords and never reuse them in production.

## 6) cPanel Deployment
Use `DEPLOY-CPANEL.md`. Summary:
1. Upload files to cPanel.
2. Point domain/subdomain document root to `public/`.
3. Run `php artisan migrate --force`; restore production data only from an approved encrypted backup.
4. Configure `.env` for production.
5. Run artisan cache clear commands (via Terminal or temporary route).
6. Ensure `storage/` and `bootstrap/cache/` are writable.

Recommended production values:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true` (HTTPS only)

## 7) Role & Access Matrix
- `super_admin`: full access
- `admin`: full tenant-level access
- `agent`: operational CRM access (students/applications workflows)
- `sub_agent`: limited read/update scope
- `student`: portal-only access

Security model:
- Tenant isolation via middleware and scoped queries
- Role-based permissions (`roles`, `permissions`, `role_permissions`)
- Password hashing via bcrypt

## 8) Student Portal Flow
1. Student logs in at `/portal/login`
2. Redirect to `/portal/dashboard`
3. Student can:
   - view universities
   - view own applications
   - upload required documents
   - send messages to CRM team

Required document types:
- Passport
- Diploma
- Transcript
- English Certificate
- Photo

## 9) Demo Data Notes
`database/schema-and-seed.sql` is a full reset installer:
- Drops existing tables
- Rebuilds schema
- Inserts demo tenant, users, roles, permissions, universities, students, applications, requests, tasks, notifications

Important:
- Import this SQL on a clean/staging database.
- Do not run on a production database with live customer data.

## 10) Troubleshooting
### A) `No application encryption key has been specified`
- Run: `php artisan key:generate`
- Then: `php artisan optimize:clear`

### B) `Undefined array key "lottery"` in session
- Ensure `config/session.php` includes:
  - `'lottery' => [2, 100],`
- Then clear cache:
  - `php artisan optimize:clear`

### C) `Target class [throttle] does not exist`
- Ensure `app/Http/Kernel.php` includes middleware alias:
  - `'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class`
- Then run:
  - `php artisan optimize:clear`

### D) 419 Page Expired
- Verify CSRF token in forms
- Ensure session driver/config is valid
- Keep browser domain/session consistent

### E) Database access errors (1045/1049)
- Verify `.env` DB credentials
- Confirm DB exists and user has privileges

## 11) Post-Install Hardening Checklist
- Change all default passwords immediately
- Set `APP_DEBUG=false` in production
- Use HTTPS and secure cookies
- Restrict DB user permissions
- Enable regular DB backups
- Configure log rotation and monitoring

## 12) Update Procedure
1. Backup files and database.
2. Replace project files with the new version.
3. Run migrations/import update SQL if provided.
4. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache` (optional)
   - `php artisan view:cache` (optional)
5. Verify login and key modules.

## 13) Support Scope
Support includes:
- Installation guidance
- Reproducible bug fixes in core package
- Clarification of default features

Support excludes:
- Custom feature development
- Third-party hosting issues outside Laravel scope
- Client-specific bespoke integrations unless agreed separately

---
For release notes and verification commands, see `docs/TESTING_AND_QA.md`.
