# Deployment checklist — branch `claude/crm-prompt-ux-review-cf864n`

Pushing to GitHub does **not** deploy. The owner deploys manually with the separate administrator account.

## What this release contains

- **Batch 1:** UX fixes (labelled student forms, error presentation, accessibility). Migration `2026_05_22_000700` was also made idempotent.
- **Batch 2:** Real XLSX exports, a categorized sidebar, and Telegram bot security and setup fixes.
- **Database:** no new tables or columns. The only migration change is the guard in `2026_05_22_000700`. It only skips creating indexes that already exist, so it changes nothing on a database where it has already run.
- **New PHP requirements:** `ext-zip` (for XLSX) and `ext-fileinfo` (for bot upload checks). Both are enabled by default on Hostinger. Without `ext-zip`, exports fall back to a clearly labelled CSV file.

## Before deploying

1. Take a full backup of the database and `storage/app`, using the existing encrypted backup command or hPanel.
2. Confirm the CI "Quality checks" job is green for the commit you deploy.
   - The `composer audit` step currently reports pre-existing advisories in Laravel and Symfony packages. Decide on a patch update separately.

## Deploy

1. Pull the branch or merge commit through Hostinger Git deployment.
2. Run `php artisan migrate --force`. It should report nothing to migrate, or only already-guarded work.
3. Rebuild the caches:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
4. Hard-refresh the browser, because `public/assets/app.css` changed.

## Telegram bot (one-time, platform admin)

1. In Telegram, open `@BotFather` and send `/newbot`. Put the token in `.env` as `TELEGRAM_BOT_TOKEN=`.
2. In `.env`, set `TELEGRAM_WEBHOOK_SECRET` to a random value of 24 or more characters. The Telegram page shows a suggestion.
   - Optionally also set `TELEGRAM_WEBHOOK_HEADER_SECRET`.
   - Make sure `APP_URL` starts with `https://`.
3. Run `php artisan config:cache`.
4. Open **CRM → Work & Communication → Telegram Bot**. Every checklist item should be green. Then press **Connect webhook**.
5. Press **Connect Telegram** and then **Open Telegram & connect**, then **Send me a test message**.

## Smoke tests after deploy

- Students: open the Students page, filter by stage, then click **Excel (.xlsx)**. The file should open in Excel with the filter shown in row 2 and the right number of rows.
- Applications, Advanced Reports, and Program Search: download each export and open it.
- Log in as an agent. The Students export should contain only that agent's students.
- Sidebar: only one item is highlighted, and groups collapse and expand.
- Telegram: the webhook shows "connected" and `/menu` answers in Telegram.

## Rollback

1. Redeploy the previous commit.
2. Rebuild the caches as in "Deploy" step 3.

No database rollback is needed, because there are no schema changes. If the webhook was connected and you roll back, it keeps working, because the old code accepts the same URL.

## Batch 3 additions (program tuition/language)

- Migration `2026_11_24_000100_ensure_fee_type_columns` is **additive and idempotent**. If the old SQL patch `docs/sql/2026_05_17_university_fee_type_and_cleanup_patch.sql` was already applied in production, it does nothing. Back up the database first, as always.
- After deploying, **re-import your original universities/programs CSV**. Rows that were saved with wrong or missing tuition or language will be **updated in place**, with no duplicates created.
- Then export Programs and filter the "Data Notes" column for "please verify" or "missing" to find anything that still needs a manual edit.
