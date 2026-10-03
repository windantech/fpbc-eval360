#!/usr/bin/env bash
#
# deploy_pull.sh — pull the latest FPC build from GitHub onto the live server.
#
# Run it from anywhere; the script finds the project root itself:
#   cd /home/vantage/domains/eval360.tech/public_html/mel/fpc
#   bash scripts/deploy_pull.sh
#
# What it does NOT touch:
#   - .env            (gitignored in this project, so git never sees it)
#   - storage/        (uploads, logs, exports — gitignored)
#   - anything untracked; `git clean` is never run
#
# FPC does not carry .env in the repository, which is why this script needs
# none of the skip-worktree protection the ATBU deploy script does.
#
set -euo pipefail

BRANCH="${1:-main}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "=============================================="
echo " FPC Eval360 — deploy from GitHub (branch: $BRANCH)"
echo " Target: $ROOT_DIR"
echo "=============================================="

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    cat <<'MSG'
ERROR: this folder is not a git repository.

First-time setup on the server:
  cd /home/vantage/domains/eval360.tech/public_html/mel
  git clone https://USERNAME:TOKEN@github.com/windantech/fpc-eval360.git fpc
  cd fpc
  bash scripts/deploy_pull.sh
MSG
    exit 1
fi

echo "[git] fetching origin/$BRANCH …"
git fetch origin "$BRANCH"

BEFORE="$(git rev-parse --short HEAD)"
git reset --hard "origin/$BRANCH"
AFTER="$(git rev-parse --short HEAD)"

if [ "$BEFORE" = "$AFTER" ]; then
    echo "[git] already up to date at $AFTER"
else
    echo "[git] $BEFORE -> $AFTER"
    git --no-pager log --oneline "$BEFORE..$AFTER" | sed 's/^/        /'
fi

# Static files only need to be readable; nothing is executed server-side yet.
find . -path ./.git -prune -o -type f -name '*.html' -exec chmod 644 {} \; 2>/dev/null || true
chmod +x scripts/*.sh 2>/dev/null || true

echo
echo "Done. https://mel.eval360.tech/fpc/"
