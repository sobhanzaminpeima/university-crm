#!/usr/bin/env bash
# Safe manual release for Hostinger shared hosting (run over SSH, in your own terminal).
#
#   bash scripts/deploy-release.sh <commit-sha> [source]
#
#   <commit-sha>  exact commit to deploy (e.g. 64b7268...)
#   [source]      "origin" (default: fetch the branch from GitHub) or a path to a .bundle file
#
# Environment overrides:
#   APP_DIR   application directory (default: ~/domains/virtuevisa.com/public_html)
#   BRANCH    branch that contains the commit (default: claude/crm-prompt-ux-review-cf864n)
#   SITE_URL  public URL for smoke tests (default: https://virtuevisa.com)
#   PHP       PHP binary (default: php)
#
# Order: preflight -> verified backup -> fetch -> checkout exact commit -> migrate -> caches -> smoke tests.
# Nothing is changed before the backup succeeds. The rollback command is printed at the end.
set -euo pipefail

SHA="${1:?usage: deploy-release.sh <commit-sha> [origin|/path/to/file.bundle]}"
SOURCE="${2:-origin}"
APP_DIR="${APP_DIR:-$HOME/domains/virtuevisa.com/public_html}"
BRANCH="${BRANCH:-claude/crm-prompt-ux-review-cf864n}"
SITE_URL="${SITE_URL:-https://virtuevisa.com}"
PHP="${PHP:-php}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$HOME/release-backups/$STAMP"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

cd "$APP_DIR" || fail "APP_DIR not found: $APP_DIR"
[ -f artisan ] || fail "No Laravel 'artisan' in $APP_DIR"
git rev-parse --git-dir >/dev/null 2>&1 || fail "$APP_DIR is not a git checkout; use Hostinger Git deployment instead."

step "Preflight"
PREV_SHA="$(git rev-parse HEAD)"
echo "Current commit : $PREV_SHA"
echo "Target commit  : $SHA"
"$PHP" -v | head -1
"$PHP" -m | grep -qi '^zip$' || echo "WARNING: ext-zip missing - exports will fall back to CSV."
"$PHP" -m | grep -qi '^fileinfo$' || fail "ext-fileinfo is required (Telegram upload checks)."
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    fail "Tracked files were edited on the server. Commit or save them before deploying (nothing was changed)."
fi

step "Backup (database + code) -> $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
echo "$PREV_SHA" > "$BACKUP_DIR/previous-commit.txt"
"$PHP" artisan crm:backup --retention=14
"$PHP" artisan crm:backup-verify
tar --exclude='./vendor' --exclude='./node_modules' --exclude='./storage/framework' --exclude='./storage/logs' \
    -czf "$BACKUP_DIR/code.tar.gz" .
cp .env "$BACKUP_DIR/env.backup" && chmod 600 "$BACKUP_DIR/env.backup"
echo "Backup OK."

step "Fetch release"
if [ "$SOURCE" = "origin" ]; then
    git fetch origin "$BRANCH"
else
    [ -f "$SOURCE" ] || fail "Bundle not found: $SOURCE"
    git bundle verify "$SOURCE"
    git fetch "$SOURCE" "$BRANCH"
fi
git cat-file -e "${SHA}^{commit}" 2>/dev/null || fail "Commit $SHA not found after fetch."
git merge-base --is-ancestor "$PREV_SHA" "$SHA" || echo "NOTE: target does not contain the current commit (deploying a different line of history)."

step "Checkout $SHA"
git checkout --detach "$SHA"
[ "$(git rev-parse HEAD)" = "$(git rev-parse "$SHA")" ] || fail "Checkout verification failed."
if ! git diff --quiet "$PREV_SHA" "$SHA" -- composer.json composer.lock; then
    echo "composer.lock changed -> installing dependencies"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

step "Migrate + caches"
"$PHP" artisan migrate --force
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart || true
"$PHP" artisan crm:ops-check || echo "WARNING: ops-check reported a problem (see above)."

step "Smoke tests"
ok=1
for path in /up /login; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL$path" || echo 000)"
    echo "$path -> $code"
    [ "$code" = "200" ] || ok=0
done

cat <<EOF

Previous commit: $PREV_SHA   (saved in $BACKUP_DIR)
ROLLBACK (code only - the new migration is additive and can stay):
  cd "$APP_DIR" && git checkout --detach $PREV_SHA && $PHP artisan optimize:clear && $PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
EOF
[ "$ok" = "1" ] && echo "Release $SHA deployed and smoke tests passed." || fail "Smoke tests failed - consider the rollback above."
