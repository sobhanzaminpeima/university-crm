# UX Usability Review — Is Vertue CRM comfortable for daily work?

Date: 2026-11-24 · Branch: `claude/crm-prompt-ux-review-cf864n` · Method: Phase C2 of `docs/prompts/CRM_MASTER_PROMPT.md`

## Evidence base and limits

- **Verified locally.** The app ran on PHP 8.3.6 and Laravel 12.69.3 with a disposable MariaDB, loaded from `database/schema-and-seed.sql` plus all migrations. Playwright/Chromium was used at 1366×900 and 375×812, logged in as the seeded super admin with a local-only password.
- **Source inspection.** I read all 58 Blade views and `public/assets/app.css`. Counts below come from `grep`.
- **Not tested.** I did not log in to production. Agent, Sub-Agent, Tenant Admin and Student roles were not walked through with real accounts, the student portal was reviewed only from source, and no real user timing studies were run.

## Verdict

| Persona | Verdict | Score (0–10) | Main reason |
|---|---|---|---|
| Admissions officer (desktop, daily) | **PARTIALLY** | 5 | Feature-rich, but forms had no labels, failed submits lost all input, and there are 25+ nav items |
| Agent / Sub-Agent (often mobile) | **PARTIALLY** | 4 | Bottom nav exists, but filters fill the first screen and wide tables need sideways scrolling |
| Persian / Turkish-speaking staff | **NO** | 2 | The UI is English-only: `lang/fa.json` has 7 keys, no view calls `__()`, and there is no `dir="rtl"` |
| Student (portal) | Not verified | — | Needs a test student account |
| **Overall** | **PARTIALLY** | **5 / 10 → about 6 after batch 1** | Usable, but slow and error-prone for nontechnical staff |

Rubric: 9–10 polished · 7–8 good with minor friction · 5–6 usable but slow or error-prone · 3–4 frequent confusion · 0–2 core tasks blocked.

## Findings

| ID | Sev | Screen | Problem (evidence) | Status |
|---|---|---|---|---|
| UX-01 | U1 | Add/Edit Student | When server validation failed, the modal reopened **empty**: no `old()` values, so typed data was lost. Measured: name and phone were `""` after submit (before), and kept (after). | **Fixed** |
| UX-02 | U1 | All forms | Placeholders stood in for labels. Only **62 `<label>` for 361 inputs/selects**. Edit dialogs showed empty boxes with no field name, and two adjacent "Lead" dropdowns (stage vs lifecycle) could not be told apart. Add Student: 0/18 labelled (before), 18/18 (after). | **Fixed for Students**; other screens open |
| UX-03 | U1 | Global | Validation errors appeared under a red **"System Error"** heading, behind the modal's backdrop, so the user could not read them while in the form. | **Fixed**: neutral wording, shown inside the form and next to each field |
| UX-04 | U1 | Global | No translation layer, so Persian/Turkish users get English only. `lang="en"` is hard-coded and there is no RTL. | Proposal P-1 |
| UX-05 | U2 | Students list | Stage labels showed raw keys (`Documents_pending`, `Visa_process`). | **Fixed** in 6 views |
| UX-06 | U2 | Sidebar | Two items were highlighted at once (`/agents/performance` lit up *Agents & Roles*; `/settings/study-catalogs` lit up *Settings*). | **Fixed** |
| UX-07 | U2 | Sidebar | About 27 links. Only 2 groups; 11 admin/config items (API Tokens, Health, SaaS…) are listed flat between daily items. | **Fixed in batch 2**: 7 collapsible categories with icons and remembered state |
| UX-08 | U2 | Topbar (mobile) | The search button was clipped off-screen at 375px (`min-width:100% !important`). | **Fixed** |
| UX-09 | U2 | A11y | Icon buttons (🔎 🔔 🌓) had no accessible name, there was no skip link or visible focus ring, the toast was not announced, and there was no `aria-expanded`. | **Fixed** in layout |
| UX-10 | U2 | Students list | Duplicate `id="global-search"` on the same page. | **Fixed** |
| UX-11 | U2 | Students list | There was no one-click way to clear filters. | **Fixed** ("Clear filters" link) |
| UX-12 | U2 | Delete | The generic `confirm('Delete student?')` did not name the student or the consequence. Delete is a soft delete (`deleted_at`), so undo is technically possible. | Confirm text **fixed**; undo is proposal P-4 |
| UX-13 | U2 | Mobile lists | Six stacked filter fields fill the whole first screen. The 10-column table needs sideways scrolling. | Proposal P-3 |
| UX-14 | U3 | Performance | One full edit `<dialog>` is rendered per table row (up to 100 per page), which makes the HTML heavy. | Proposal P-5 |
| UX-15 | U3 | Search | No keyboard shortcut to reach search. | **Fixed** (`/` focuses search, `Esc` closes panels) |
| ENG-01 | P1 | Install | `2026_05_22_000700` was not idempotent and reused the index name `student_messages_tenant_student_idx` from `2026_04_17_000002`, so a fresh install (schema dump + `migrate`) stopped halfway with error 1061. | **Fixed** with `Schema::hasIndex` guards |
| EXP-01 | U1 | Exports | "Export Excel" produced a tab-separated text file named `.xls`. Students and Applications had no export at all. The university search export contained only the 20 rows on screen. | **Fixed in batch 2**: real styled `.xlsx` everywhere |
| SEC-02 | P1 | Exports | The program search export ignored the agent "own universities" rule. The Advanced Report exposed all tenant students to agents who have `reports.view`. | **Fixed in batch 2** |
| SEC-03 | P1 | Telegram | Agents could change any application's status and read or reply to any student's messages in the tenant through bot callbacks. Bot uploads were stored publicly with the sender's file extension. User data was not HTML-escaped. | **Fixed in batch 2** (11 automated checks pass) |
| SEC-01 | P1 | Dependencies | `composer audit --locked` reports 8 advisories in 5 packages (laravel/framework, symfony/http-foundation ×4, http-kernel, mime, routing). This also fails the existing CI audit step. | Open: patch-level `composer update` needs owner approval |

## Before / after (local, real browser)

| | Before | After |
|---|---|---|
| Add Student after validation error (desktop) | `screenshots/before-desktop-add-student-error.png` | `screenshots/after-desktop-add-student-error.png` |
| Add Student after validation error (mobile) | `screenshots/before-mobile-add-student-error.png` | `screenshots/after-mobile-add-student-error.png` |
| Students list (mobile) | `screenshots/before-mobile-students.png` | `screenshots/after-mobile-students.png` |

Measured (Playwright): horizontal overflow 0 px at 1366 and 375 both before and after, no console errors, labelled fields 0/18 → 18/18, input kept after error: no → yes.

## Proposals that need owner approval

| ID | Proposal | User value | Effort | Risk |
|---|---|---|---|---|
| P-1 | **Full i18n + RTL.** Wrap all view strings in `__()`, add a locale switcher (user preference plus cookie), set `<html lang dir>`, use logical CSS properties, and wrap emails and phone numbers in `<bdi dir="ltr">`. | Very high for FA/TR teams | L (3–5 days) | Medium: touches every view |
| P-2 | ~~**Navigation IA.**~~ Done in batch 2. Use the groups *Work* (Dashboard, Students, Applications, Pipeline, Tasks, Messages), *Catalog* (Universities, Scholarships, Calendar), *Insights* (Reports, Performance, Finance) and *Admin* (collapsed by default: Users, Templates, Automation, API, Health, Settings, SaaS). Remember the collapsed state. | Faster wayfinding | S | Low |
| P-3 | **Mobile list mode.** Collapse filters into a "Filters (n)" disclosure and show card rows below 760px (name, stage, agent, one primary action). | Agents on phones | M | Low |
| P-4 | **Undo delete.** Show a toast with *Undo* for 10 s using the existing soft-delete. Requires restoring the portal user too, so the business rule needs approval. | Fewer costly mistakes | S–M | Medium (account re-activation) |
| P-5 | **Single edit drawer.** Load one edit form on demand (or go to `/students/{id}`) instead of N hidden dialogs. | Faster pages | M | Low |
| P-6 | **Stage model clarity.** `stage` (10 values) and `lifecycle_stage` (6 values) overlap and confuse staff. Derive lifecycle from stage, or explain the difference with help text. | Fewer wrong entries | S (help text) / M (derive) | Business rule, needs approval |
| P-7 | **Quick-create and command palette.** A "+ New" menu in the topbar (student, task, application) and `Ctrl+K` search across entities. | Fewer clicks for top tasks | M | Low |
| P-8 | **Saved views and bulk actions** on Students and Applications (assign agent, change stage, export selection). | Big time saver | M–L | Must respect tenant/RBAC checks |
| P-9 | Roll out the labelled-field pattern (`students/_form-fields.blade.php`) to Applications, Universities, Tasks, Users and the portal. | Consistency | M | Low |

## Re-run instructions

1. Start MariaDB and import `database/schema-and-seed.sql` into a throwaway DB.
2. Run `php artisan migrate`, then `php artisan serve`.
3. Use Playwright at 1366 and 375. Submit Add Student with an invalid email: the form should reopen with the typed values kept and the field errors shown inline.
