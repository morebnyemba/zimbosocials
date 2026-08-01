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

# ── 4. Gate on AI prompt accuracy ─────────────────────────────────────────────
# Only worth real, billed Gemini calls when something that could change the
# model's behaviour actually moved — the prompt, the resolver, or the golden
# set itself. A prompt/model change that quietly degrades flow accuracy has
# shipped before (see PROMPT_VERSION history); this catches it before it's
# live, using the image already built in step 2, not yet serving traffic.
SKIP_AI_EVAL="${SKIP_AI_EVAL:-0}"
AI_EVAL_MIN_ACCURACY="${AI_EVAL_MIN_ACCURACY:-85}"
RUN_AI_EVAL=1
if [[ "$PULL" == "1" ]]; then
    if [[ "$BEFORE" == "$AFTER" ]] \
        || ! git diff --name-only "$BEFORE" "$AFTER" | grep -qE 'GeminiProvider\.php|IntentEngine\.php|whatsapp-ai-golden\.json'
    then
        RUN_AI_EVAL=0
    fi
fi
[[ "$SKIP_AI_EVAL" == "1" ]] && RUN_AI_EVAL=0

if [[ "$RUN_AI_EVAL" == "1" ]]; then
    say "Checking AI prompt accuracy (floor: ${AI_EVAL_MIN_ACCURACY}%)"
    EVAL_OUT="$(docker compose run --rm --no-deps app php artisan whatsapp:ai-eval 2>&1)" || true
    echo "$EVAL_OUT"
    ACCURACY="$(echo "$EVAL_OUT" | grep -oE '\([0-9]+%\)' | tail -1 | tr -d '(%)')"
    if [[ -z "$ACCURACY" ]]; then
        echo "✗ Could not read an accuracy figure from whatsapp:ai-eval (missing GEMINI_API_KEY? Gemini down?)."
        echo "  Refusing to deploy an unverified prompt/model change. Re-run with SKIP_AI_EVAL=1 to override."
        exit 1
    fi
    if (( ACCURACY < AI_EVAL_MIN_ACCURACY )); then
        echo "✗ AI flow accuracy is ${ACCURACY}%, below the ${AI_EVAL_MIN_ACCURACY}% floor. Not deploying this prompt/model change."
        exit 1
    fi
    echo "✓ AI flow accuracy ${ACCURACY}% ≥ ${AI_EVAL_MIN_ACCURACY}%."
else
    echo "    (skipping AI eval — no prompt/resolver/golden-set changes detected)"
fi

# ── 5. Swap ───────────────────────────────────────────────────────────────────
# --wait blocks until the web container's healthcheck passes, so a container
# that cannot actually answer is never left in place silently.
say "Starting new containers"
docker compose up -d --wait --wait-timeout 120

# ── 6. Prime ──────────────────────────────────────────────────────────────────
say "Refreshing caches"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache

# ── 7. Prove it ───────────────────────────────────────────────────────────────
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
