# Architecture

## Application shape

Virtue Visa CRM is a server-rendered Laravel application. Blade views provide the administrator and student interfaces; controllers and services implement business workflows; MySQL stores tenant, identity, CRM and operational records.

```text
Browser / Telegram
        |
 HTTPS + middleware
        |
Laravel routes and controllers
        |
Policies / permissions / tenant scope
        |
Services, jobs and scheduled commands
        |
MySQL + private storage + mail/Telegram APIs
```

## Tenancy and authorization

Business records carry tenant ownership. Queries and mutations must scope records to the authenticated tenant, except explicitly authorized super-admin operations. Roles receive permissions through database relationships, so capability checks remain independent of navigation visibility.

## Important boundaries

- `routes/`: web, API and integration entry points
- `app/Http/`: middleware and request handling
- `app/Models/`: persistent domain entities
- `app/Services/`: reusable business and integration logic
- `app/Console/Commands/`: maintenance, backup, SLA and Telegram commands
- `database/migrations/`: canonical schema evolution
- `resources/views/`: Blade user interface
- `storage/`: private runtime files and logs
- `public/`: web-accessible assets only

## Deployment model

The `main` branch is deployed to Hostinger. The repository root can operate as `public_html` through the supplied front controller, while requests are routed into Laravel's public directory. Environment configuration and customer data remain outside Git.

## Data recovery

Schema changes are forward migrations. Runtime backups are encrypted and verified before being considered recoverable. The encryption key and `APP_KEY` must be held separately from the hosting account.

