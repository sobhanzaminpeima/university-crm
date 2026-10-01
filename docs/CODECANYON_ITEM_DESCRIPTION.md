# CodeCanyon Item Description (Ready to Copy)

## Item Title
Vertue CRM + Student Portal (Laravel) | Multi-Tenant Education SaaS, Admissions, Applications, Documents & Agent Workflow

## Short Description
Vertue CRM is a production-focused Laravel SaaS solution for student recruitment agencies.  
It includes a complete CRM for Admin/Agents and a dedicated Student Portal with secure login, document uploads, application tracking, messaging, and request workflow.

## Price Suggestion
$59 (Regular License)

## Full Description
Build and scale your education recruitment business with an all-in-one, modern, multi-tenant CRM and Student Portal.

Vertue CRM is designed for real agency operations:
- Lead and student lifecycle management
- University and program management
- Applications pipeline and status tracking
- Task assignment and follow-up
- Student requests approval flow
- Document management and verification
- Notifications and internal collaboration
- Dedicated student portal access

No demo-only fake flows. Core modules are connected to database operations and role-based access controls.

## Main Features
- Multi-tenant architecture (`tenant_id` isolation)
- Role-based access (Super Admin, Admin, Agent, Sub-Agent, Student)
- Secure authentication (bcrypt password hashing)
- CRM + Student Portal separation
- Students CRUD
- Universities CRUD
- Applications CRUD
- Student Requests (approve/reject)
- Tasks management
- Scholarships module
- Notifications center
- Finance module
- Portal messaging (student to staff)
- Portal document upload and tracking
- Audit log support

## Student Portal Features
- Student login (`/portal/login`)
- Dashboard with student-specific data
- Applications status visibility
- Document uploads (Passport, Diploma, Transcript, English Certificate, Photo)
- Messaging with agency team
- University browsing

## Technical Stack
- Laravel 12
- PHP 8.3+
- MySQL
- Blade UI
- JWT support for API endpoints

## What You Get
- Full Laravel source code
- SQL schema + demo data (`database/schema-and-seed.sql`)
- cPanel deployment guide
- Complete English documentation
- QA/testing checklist

## Demo Credentials
Demo credentials must be supplied privately for a dedicated demo environment. No reusable administrator password is included in the public package.

## Installation Summary
1. Upload files
2. Configure `.env`
3. Run Laravel migrations on a clean database
4. Run:
   - `composer install`
   - `php artisan key:generate`
   - `php artisan storage:link`
   - `php artisan optimize:clear`
5. Open CRM and Portal URLs

Full guide:
- `docs/CODECANYON_DOCUMENTATION.md`

## Ideal For
- Student recruitment agencies
- Education consultancies
- Admission and placement teams
- Multi-agent education businesses

## Changelog (Initial Release)
### v1.0.0
- Initial CodeCanyon release
- CRM + Student Portal core modules included
- Role/permission model and tenant isolation
- Demo data and documentation package included

## Support
Support includes:
- Installation help
- Bug fixes for core package behavior
- Guidance for documented features

Support excludes:
- Custom feature development
- 3rd-party custom integrations unless agreed separately

## Notes
- Requires PHP 8.3+ and MySQL.
- Use HTTPS and secure production settings on live servers.
- Provision unique credentials and keep production secrets outside the repository.
