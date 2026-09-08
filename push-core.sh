#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# push-core.sh — Push changes from core/ back to monolith
#
# Usage: ./push-core.sh
#
# WARNING: This pushes ALL commits that touched core/ back to
# the monolith repository. Only use when you intentionally
# modified files inside core/ for upstream contribution.
# ============================================================

MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"
MONOLITH_REMOTE="${MONOLITH_REMOTE:-monolith}"
MONOLITH_BRANCH="${MONOLITH_BRANCH:-main}"
CORE_PREFIX="${CORE_PREFIX:-core}"

# ── 1. Ensure monolith remote exists ────────────────────────
echo " Checking remote '${MONOLITH_REMOTE}'..."

if ! git remote get-url "${MONOLITH_REMOTE}" &>/dev/null; then
    echo " Remote '${MONOLITH_REMOTE}' not found. Adding ${MONOLITH_URL} ..."
    git remote add "${MONOLITH_REMOTE}" "${MONOLITH_URL}"
    echo " Remote '${MONOLITH_REMOTE}' added."
else
    CURRENT_URL=$(git remote get-url "${MONOLITH_REMOTE}")
    echo " Remote '${MONOLITH_REMOTE}' exists → ${CURRENT_URL}"
fi

# ── 2. Check for uncommitted changes ────────────────────────
echo ""
echo " Checking working tree..."

if ! git diff-index --quiet HEAD --; then
    echo " Working tree has uncommitted changes. Commit or stash them first."
    git status --short
    exit 1
fi

# ── 3. Show what will be pushed ─────────────────────────────
echo ""
echo " Commits that touched '${CORE_PREFIX}/' (not yet in ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}):"

# Get the last subtree split commit
LAST_SPLIT=$(git log --oneline --grep="git-subtree-dir: ${CORE_PREFIX}" -1 --format="%H" 2>/dev/null || echo "")

if [ -n "${LAST_SPLIT}" ]; then
    COMMITS_TO_PUSH=$(git log --oneline "${LAST_SPLIT}..HEAD" -- "${CORE_PREFIX}/" 2>/dev/null || echo "")
else
    COMMITS_TO_PUSH=$(git log --oneline -- "${CORE_PREFIX}/" 2>/dev/null || echo "")
fi

if [ -z "${COMMITS_TO_PUSH}" ]; then
    echo "   (no local commits to push)"
    echo ""
    echo " Nothing to push. '${CORE_PREFIX}/' has no changes to send upstream."
    exit 0
fi

echo "${COMMITS_TO_PUSH}"

# ── 4. Show files that will be pushed ───────────────────────
echo ""
echo " Changed files in '${CORE_PREFIX}/':"
if [ -n "${LAST_SPLIT}" ]; then
    git diff --stat "${LAST_SPLIT}..HEAD" -- "${CORE_PREFIX}/" 2>/dev/null || echo "(no diff)"
else
    git diff --stat HEAD~1..HEAD -- "${CORE_PREFIX}/" 2>/dev/null || echo "(no diff)"
fi

# ── 5. Confirmation ─────────────────────────────────────────
echo ""
echo "  WARNING: This will push changes from '${CORE_PREFIX}/' to"
echo "   ${MONOLITH_REMOTE} (${MONOLITH_URL}) on branch '${MONOLITH_BRANCH}'."
echo ""
read -r -p "Continue? [y/N] " CONFIRM

if [ "${CONFIRM}" != "y" ] && [ "${CONFIRM}" != "Y" ]; then
    echo " Push cancelled."
    exit 0
fi

# ── 6. Push subtree ─────────────────────────────────────────
echo ""
echo " Pushing '${CORE_PREFIX}/' to ${MONOLITH_REMOTE} ${MONOLITH_BRANCH}..."
git subtree push --prefix="${CORE_PREFIX}/" "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}"

echo ""
echo " Push complete."
