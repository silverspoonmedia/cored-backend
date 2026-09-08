#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# update-core.sh — Pull latest monolith updates into core/
#
# Usage: ./update-core.sh
#
# Configurable remote URL:
#   Update MONOLITH_REMOTE and MONOLITH_BRANCH below.
#   The script auto-registers the remote if missing.
# ============================================================

MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"
MONOLITH_REMOTE="${MONOLITH_REMOTE:-monolith}"
MONOLITH_BRANCH="${MONOLITH_BRANCH:-main}"
CORE_PREFIX="${CORE_PREFIX:-core}"

# ── 1. Ensure monolith remote exists ────────────────────────
echo "Checking remote '${MONOLITH_REMOTE}'..."

if ! git remote get-url "${MONOLITH_REMOTE}" &>/dev/null; then
    echo "Remote '${MONOLITH_REMOTE}' not found. Adding ${MONOLITH_URL} ..."
    git remote add "${MONOLITH_REMOTE}" "${MONOLITH_URL}"
    echo "Remote '${MONOLITH_REMOTE}' added."
else
    CURRENT_URL=$(git remote get-url "${MONOLITH_REMOTE}")
    echo "Remote '${MONOLITH_REMOTE}' exists → ${CURRENT_URL}"
fi

# ── 2. Fetch latest from monolith ───────────────────────────
echo ""
echo "Fetching ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}..."
git fetch "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}"

# ── 3. Check if there's anything new ────────────────────────
echo ""
echo "Checking for new commits..."

BEHIND_COUNT=$(git rev-list --count HEAD.."${MONOLITH_REMOTE}/${MONOLITH_BRANCH}" 2>/dev/null || echo "0")

if [ "${BEHIND_COUNT}" -eq 0 ]; then
    echo "'${CORE_PREFIX}/' is already up-to-date with ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}."
    exit 0
fi

echo "${BEHIND_COUNT} new commit(s) available from ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}."

# ── 4. Show what's coming ───────────────────────────────────
echo ""
echo "Incoming commits:"
git log --oneline HEAD.."${MONOLITH_REMOTE}/${MONOLITH_BRANCH}"

# ── 5. Pull subtree with squash ─────────────────────────────
echo ""
echo "Pulling ${MONOLITH_REMOTE}/${MONOLITH_BRANCH} into '${CORE_PREFIX}/' (--squash)..."
git subtree pull --prefix="${CORE_PREFIX}/" "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}" --squash

# ── 6. Post-pull checks ────────────────────────────────────
echo ""
echo "Subtree pull complete."

echo ""
echo "Files changed:"
git diff --stat HEAD~1 -- "${CORE_PREFIX}/" 2>/dev/null || echo "(first pull or no diff available)"

echo ""
echo "Checking for config differences between '${CORE_PREFIX}/config/' and 'config/'..."
if diff -rq config/ "${CORE_PREFIX}/config/" 2>/dev/null | grep -q "differ"; then
    echo "  Config files differ! Review and manually merge changes:"
    diff -rq config/ "${CORE_PREFIX}/config/" | grep "differ"
else
    echo "No config differences detected."
fi

echo ""
echo " Checking for new Composer dependencies..."
echo "   Compare '${CORE_PREFIX}/composer.json' with your 'composer.json'."
echo "   Run 'composer install' if dependencies changed."

echo ""
echo " Checking for new migrations..."
NEW_MIGRATIONS=$(find "${CORE_PREFIX}/database/migrations/" -name "*.php" -newer vendor/autoload.php 2>/dev/null | wc -l || echo "0")
if [ "${NEW_MIGRATIONS}" -gt 0 ]; then
    echo "  New migrations detected. Run: php artisan migrate"
else
    echo "No new migrations detected."
fi

echo ""
echo " Done. '${CORE_PREFIX}/' is now up-to-date."
