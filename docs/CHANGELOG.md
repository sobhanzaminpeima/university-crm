# Change log

## 2026-11-24 — UX usability batch 1

- Student add/edit forms: visible labels, grouped sections, inline field errors, and typed values kept after a failed submit (`students/_form-fields.blade.php`).
- Global error summary reworded from "System Error" to a neutral message, rendered inline and focusable.
- Layout accessibility: skip link, accessible names on icon buttons, `aria-expanded` on toggles, live-region toast, visible focus ring, reduced-motion support, `/` shortcut to focus search, `Esc` closes panels.
- Fixed double-highlighted sidebar items, humanized stage labels, fixed the clipped search button on mobile, added "Clear filters".
- Migration `2026_05_22_000700` made idempotent (fresh installs failed with duplicate index 1061). No schema change for databases where it already ran.
- Added `docs/prompts/CRM_MASTER_PROMPT.md` (with the Phase C2 usability verdict) and `docs/audits/UX_USABILITY_REVIEW.md`.

## 2026-10-02

- Updated README and production documentation for Laravel 12 and PHP 8.3.
- Documented Hostinger Git deployment, scheduler, queue, SMTP, Telegram, Cloudflare and rollback procedures.
- Removed published demo passwords from active documentation.
- Added architecture, security and production-operations guides.

## 2026-10-01

- Added encrypted production backup creation and verification.
- Added operational health checks, scheduler support and queue database tables.
- Added password recovery and encrypted stored integration credentials.
- Added production security headers, authentication throttles and readiness checks.
- Added Telegram role permission support.
- Updated Hostinger routing and deployment compatibility.

