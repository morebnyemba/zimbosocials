#!/usr/bin/env bash
#
# ZimboSocials — deploy the current branch with minimal interruption.
#
# The naive `docker compose up -d --build` is what makes deploys feel risky: it
# builds, then swaps every container at once, and if the new image is broken the
# site is already down before anyone notices. This does the slow, failure-prone
# work FIRST — while the old containers keep serving — and only swaps once the
# new image has been built and proven able to answer.
#
# Usage:
#   ./scripts/deploy.sh            # pull, build, migrate, swap, verify
#   ./scripts/deploy.sh --no-pull  # deploy the working tree as-is
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PULL=1
[[ "${1:-}" == "--no-pull" ]] && PULL=0

say() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }

# ── 1. Get the code ───────────────────────────────────────────────────────────
if [[ "$PULL" == "1" ]]; then
    say "Pulling"
    BEFORE="$(git rev-parse HEAD)"
    git pull --ff-only
    AFTER="$(git rev-parse HEAD)"
    [[ "$BEFORE" == "$AFTER" ]] && echo "    (already up to date)"
fi

# ── 2. Build BEFORE touching anything that is serving ─────────────────────────
# A compile error, a failed npm ci or the Tailwind guard tripping all surface
# here, with the current release still up and untouched.
say "Building images (site stays up)"
docker compose build

# ── 3. Migrate ────────────────────────────────────────────────────────────────
# Run from a throwaway container on the NEW image, so schema changes land before
# the new code serves them. Additive migrations are safe here; a destructive one
# (dropping or renaming a column the running release still reads) is not, and
# should be split across two deploys.
say "Migrating"
docker compose run --rm --no-deps app php artisan migrate --force

# ── 4. Swap ───────────────────────────────────────────────────────────────────
# --wait blocks until the web container's healthcheck passes, so a container
# that cannot actually answer is never left in place silently.
say "Starting new containers"
docker compose up -d --wait --wait-timeout 120

# ── 5. Prime ──────────────────────────────────────────────────────────────────
say "Refreshing caches"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache

# ── 6. Prove it ───────────────────────────────────────────────────────────────
say "Verifying"
code="$(docker compose exec -T web wget -qO- -S -T 5 http://127.0.0.1/up 2>&1 | awk '/HTTP\//{print $2; exit}')"
if [[ "$code" != "200" ]]; then
    echo "✗ Health check returned '${code:-no response}'."
    echo "  The previous images are still on this host — roll back with:"
    echo "    git reset --hard HEAD~1 && ./scripts/deploy.sh --no-pull"
    docker compose logs --tail=40 app
    exit 1
fi

echo "✓ Healthy."
docker compose ps --format 'table {{.Service}}\t{{.Status}}'

cat <<'NOTE'

Note: the swap in step 4 is a few seconds of restart, not true zero downtime.
Removing that entirely needs two app/web replicas behind the proxy, drained one
at a time — worth doing if the gap ever matters, but it is not free complexity.
NOTE
