#!/usr/bin/env bash
#
# ZimboSocials — full migration backup.
#
# Bundles EVERYTHING needed to stand the site up somewhere else:
#   • the database (mysqldump, or a copy of the sqlite file)
#   • storage/app  — payment proofs and other user uploads
#   • .env         — credentials and API keys
#
# Produces one timestamped .tar.gz. Run it on the OLD server, copy the archive
# across, then run migrate-restore.sh on the NEW one.
#
# Usage:
#   ./scripts/migrate-backup.sh [output-dir]
#
# cPanel note: php/mysqldump are often not on PATH in jailshell. Override with
#   PHP_BIN=/opt/alt/php83/usr/bin/php ./scripts/migrate-backup.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$APP_DIR/storage/app/migration}"
PHP_BIN="${PHP_BIN:-php}"
STAMP="$(date +%Y%m%d-%H%M%S)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "✗ No .env found in $APP_DIR — is this the right directory?" >&2
    exit 1
fi

# Read a value from .env without sourcing it (values may contain spaces/#).
env_get() {
    sed -n "s/^$1=//p" .env | tail -n1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_CONNECTION="$(env_get DB_CONNECTION)"; DB_CONNECTION="${DB_CONNECTION:-mysql}"

echo "→ Backing up ZimboSocials ($DB_CONNECTION) …"
mkdir -p "$WORK/backup" "$OUT_DIR"

# ── 1. Database ───────────────────────────────────────────────────────────────
if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    SQLITE_PATH="$(env_get DB_DATABASE)"; SQLITE_PATH="${SQLITE_PATH:-$APP_DIR/database/database.sqlite}"
    [[ -f "$SQLITE_PATH" ]] || { echo "✗ sqlite file not found: $SQLITE_PATH" >&2; exit 1; }
    cp "$SQLITE_PATH" "$WORK/backup/database.sqlite"
    echo "  ✓ sqlite database copied"
else
    DB_HOST="$(env_get DB_HOST)";     DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="$(env_get DB_PORT)";     DB_PORT="${DB_PORT:-3306}"
    DB_NAME="$(env_get DB_DATABASE)"
    DB_USER="$(env_get DB_USERNAME)"
    DB_PASS="$(env_get DB_PASSWORD)"
    [[ -n "$DB_NAME" ]] || { echo "✗ DB_DATABASE is empty in .env" >&2; exit 1; }

    # Password via MYSQL_PWD so it never appears in the process list.
    # --single-transaction keeps the dump consistent without locking the site.
    MYSQL_PWD="$DB_PASS" mysqldump \
        --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" \
        --single-transaction --quick --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "$DB_NAME" > "$WORK/backup/database.sql"
    echo "  ✓ mysqldump: $(du -h "$WORK/backup/database.sql" | cut -f1)"
fi

# ── 2. User uploads ───────────────────────────────────────────────────────────
# storage/app holds payment proofs — losing these means losing evidence of
# customer payments, so they travel with the database.
if [[ -d storage/app ]]; then
    tar -czf "$WORK/backup/storage-app.tar.gz" \
        --exclude='storage/app/migration' \
        --exclude='storage/app/backups' \
        storage/app
    echo "  ✓ storage/app: $(du -h "$WORK/backup/storage-app.tar.gz" | cut -f1)"
fi

# ── 3. Configuration ──────────────────────────────────────────────────────────
cp .env "$WORK/backup/env"
echo "  ✓ .env"

# A manifest so the restore side knows what it is looking at.
cat > "$WORK/backup/MANIFEST.txt" <<EOF
ZimboSocials migration backup
created:     $(date -u +"%Y-%m-%dT%H:%M:%SZ")
source host: $(hostname 2>/dev/null || echo unknown)
source path: $APP_DIR
db driver:   $DB_CONNECTION
git commit:  $(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo "n/a")
EOF

ARCHIVE="$OUT_DIR/zimbosocials-migration-$STAMP.tar.gz"
tar -czf "$ARCHIVE" -C "$WORK" backup
chmod 600 "$ARCHIVE"   # contains .env — keep it private

echo
echo "✓ Backup complete: $ARCHIVE  ($(du -h "$ARCHIVE" | cut -f1))"
echo
echo "This archive contains .env (API keys, DB password). Move it over SSH/SCP —"
echo "never through a public URL or email. On the new server:"
echo "  ./scripts/migrate-restore.sh $(basename "$ARCHIVE")"
