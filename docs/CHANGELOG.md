# Change log

## 2026-11-24 — Program tuition/language data fixes (batch 3)

- Root cause of missing/incorrect tuition in program exports: the CSV importer parsed "4,000" as 4, "$4,500" as 4.5, and dropped "USD 3500", "12,500.50" and "3,750 / year". The fee parser now handles thousands separators, decimal commas, currency symbols/codes and suffixes; ranges are kept in the program notes and reported instead of being silently dropped.
- Root cause of missing languages: only exact values were recognised; "English / Turkish", "EN/TR", "Turkish, English", "İngilizce", "Ingilizce" are now understood. Unrecognised languages (e.g. Russian) are kept in the notes and reported.
- Re-importing a program now updates the existing row (identity: university + degree + name + thesis type + language) instead of inserting a duplicate next to the old empty row; empty cells no longer erase saved values.
- Programs export: honours the University name, City and Type filters (previously ignored, so unrelated universities were mixed in); falls back to the university's language/currency, shows the university tuition range, and explains every gap in a "Data Notes" column ("Tuition looks too low — please verify" flags values corrupted by the old importer).
- New idempotent migration `2026_11_24_000100_ensure_fee_type_columns`: adds `universities.tuition_fee_type` / `university_programs.fee_type` only when missing (they previously existed only via a manual SQL patch, so fresh installs crashed on the Universities page).
- Import result message says "Please review" instead of "rows were skipped" for warnings.

## 2026-11-24 — Exports, navigation and Telegram bot (batch 2)

### Excel exports
- New dependency-free XLSX writer (`app/Support/Export/XlsxWriter.php`, needs only ext-zip): title + filter summary, styled frozen header, auto-filter, zebra rows, borders, typed numbers/money/dates, auto column widths, landscape print setup with repeated header and page numbers.
- `SpreadsheetExport` helper: consistent `<name>-YYYY-MM-DD_HHMMSS.xlsx` filenames, correct MIME type, temp-file streaming (bounded memory), UTF-8 CSV with BOM and formula-injection protection, CSV fallback when ext-zip is missing (never labelled .xlsx).
- Students and Applications list exports added (same filters, tenant scope and agent ownership as the lists; passport numbers never exported; student exports are audit-logged).
- Advanced Report "Excel" is now a real .xlsx (was a tab-separated file named .xls). CSV export gained BOM and injection protection.
- Search exports (universities/programs) are now .xlsx with more columns (currency, duration, discounted fees, program counts).
- Fixed: university search export only contained the first 20 rows shown on screen.
- Fixed: program search export ignored the agent "own universities only" rule.
- Fixed: Advanced Report showed/exported every tenant student to agents with reports.view; it now applies the same ownership rule as the Students list.
- Report "PDF" no longer downloads an .html file when no PDF engine is installed; it opens a print-ready page.

### Navigation
- Sidebar reorganised into collapsible categories (Admissions, Universities & Programs, Work & Communication, Finance & Reports, Team & Automation, System, SaaS Platform) with icons, a single active item, remembered open/closed state and the signed-in user shown at the bottom. Groups with no permitted items are hidden.
- Wide tables now scroll inside their card instead of widening the page.

### Telegram bot
- Security: all bot messages are HTML-escaped centrally (user data could break messages or inject clickable links).
- Security: agents could change the status of any application and read/reply to any student's messages in the tenant through bot callbacks; ownership and permission checks now match the web UI.
- Security: files uploaded through the bot were saved to public storage with the sender's extension (e.g. `.php`, `.html`); they are now content-checked (PDF/JPG/PNG/WEBP, ≤10 MB) and saved with a server-chosen extension.
- Reliability: webhook always acknowledges updates (a crash no longer makes Telegram retry the same update forever), duplicate deliveries are ignored, API failures are logged without the token, unparseable messages are resent as plain text, `telegram:poll` backs off on errors instead of spinning.
- Easier setup: new Telegram page with a setup checklist for the platform admin (token check, webhook secret with a generated suggestion, HTTPS check, webhook status/last error) and one-click "Connect webhook", "Register command menu", "Disconnect webhook".
- Easier linking: one-tap "Open Telegram & connect" deep link (`/start link_<code>`), copy button for the manual code, "Send me a test message".

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

