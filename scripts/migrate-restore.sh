#!/usr/bin/env bash
#
# ZimboSocials — restore a migration backup on a NEW server.
#
# Takes the archive produced by migrate-backup.sh and rebuilds the site:
#   • restores .env (yours is kept as .env.before-restore)
#   • imports the database
#   • restores storage/app (payment proofs)
#   • runs migrations, links storage, primes caches
#
# Usage:
#   ./scripts/migrate-restore.sh zimbosocials-migration-20260721-201500.tar.gz
#   DOCKER=1 ./scripts/migrate-restore.sh <archive>     # run artisan/mysql in compose
#
# It OVERWRITES the database and .env, so it asks first unless FORCE=1.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ARCHIVE="${1:-}"
PHP_BIN="${PHP_BIN:-php}"
DOCKER="${DOCKER:-0}"
FORCE="${FORCE:-0}"

cd "$APP_DIR"

if [[ -z "$ARCHIVE" || ! -f "$ARCHIVE" ]]; then
    echo "Usage: $0 <migration-archive.tar.gz>" >&2
    exit 1
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
tar -xzf "$ARCHIVE" -C "$WORK"
SRC="$WORK/backup"
[[ -d "$SRC" ]] || { echo "✗ Archive doesn't look like a migration backup." >&2; exit 1; }

echo "─────────────────────────────────────────────"
cat "$SRC/MANIFEST.txt" 2>/dev/null || echo "(no manifest)"
echo "─────────────────────────────────────────────"

if [[ "$FORCE" != "1" ]]; then
    echo
    echo "This will OVERWRITE the database and .env in $APP_DIR."
    read -r -p "Continue? [y/N] " reply
    [[ "$reply" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 1; }
fi

# Run artisan (and mysql) inside the container when the stack is dockerised.
artisan() {
    if [[ "$DOCKER" == "1" ]]; then docker compose exec -T app php artisan "$@";
    else "$PHP_BIN" artisan "$@"; fi
}

read_env() {  # read_env <file> <key>
    sed -n "s/^$2=//p" "$1" | tail -n1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

# ── 1. .env ───────────────────────────────────────────────────────────────────
if [[ -f .env ]]; then
    cp .env ".env.before-restore.$(date +%Y%m%d-%H%M%S)"
    echo "  ↩ existing .env saved as .env.before-restore.*"
fi
cp "$SRC/env" .env
chmod 600 .env
echo "  ✓ .env restored"

DB_CONNECTION="$(read_env .env DB_CONNECTION)"; DB_CONNECTION="${DB_CONNECTION:-mysql}"

# ── 2. Database ───────────────────────────────────────────────────────────────
if [[ -f "$SRC/database.sqlite" ]]; then
    TARGET="$(read_env .env DB_DATABASE)"; TARGET="${TARGET:-$APP_DIR/database/database.sqlite}"
    mkdir -p "$(dirname "$TARGET")"
    cp "$SRC/database.sqlite" "$TARGET"
    echo "  ✓ sqlite database restored"
elif [[ -f "$SRC/database.sql" ]]; then
    DB_HOST="$(read_env .env DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="$(read_env .env DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
    DB_NAME="$(read_env .env DB_DATABASE)"
    DB_USER="$(read_env .env DB_USERNAME)"
    DB_PASS="$(read_env .env DB_PASSWORD)"

    echo "  → importing into $DB_NAME (this can take a minute) …"
    if [[ "$DOCKER" == "1" ]]; then
        docker compose exec -T db sh -c \
            "MYSQL_PWD='$DB_PASS' mariadb -u'$DB_USER' '$DB_NAME'" < "$SRC/database.sql"
    else
        MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" \
            --user="$DB_USER" --default-character-set=utf8mb4 "$DB_NAME" < "$SRC/database.sql"
    fi
    echo "  ✓ database imported"
else
    echo "  ! no database in archive — skipping"
fi

# ── 3. Uploads ────────────────────────────────────────────────────────────────
if [[ -f "$SRC/storage-app.tar.gz" ]]; then
    tar -xzf "$SRC/storage-app.tar.gz" -C "$APP_DIR"
    echo "  ✓ storage/app restored (payment proofs)"
fi

# ── 4. Bring the app up ───────────────────────────────────────────────────────
echo "  → finishing off …"
artisan migrate --force
artisan storage:link 2>/dev/null || echo "    (storage:link skipped — link may already exist)"
artisan config:clear
artisan cache:clear

# Ownership matters when php-fpm runs as www-data.
if [[ "$DOCKER" != "1" ]] && id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

echo
echo "✓ Restore complete."
echo
echo "Still to do by hand:"
echo "  1. Point DNS at this server, and issue TLS for the domain."
echo "  2. Update the Meta webhook URL to https://<domain>/webhooks/whatsapp"
echo "     — until you do, the bot receives nothing."
echo "  3. Check .env for values that are host-specific (APP_URL, DB_HOST, mail)."
echo "  4. Confirm the scheduler and queue worker are running:"
echo "       docker compose ps        # or: php artisan schedule:list"
