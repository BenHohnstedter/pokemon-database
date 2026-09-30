#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

PROJECT="$(basename "$REPO_ROOT")"
BRANCH="${BRANCH:-main}"

echo "[homelab] $PROJECT | Branch: $BRANCH"

if [ "$(git symbolic-ref --short -q HEAD 2>/dev/null || echo "$BRANCH")" != "$BRANCH" ]; then
    git checkout -q "$BRANCH" 2>/dev/null || git checkout -q -b "$BRANCH"
fi

git fetch -q origin "$BRANCH"
git reset -q --hard "origin/$BRANCH"

if ! git ls-remote --heads origin develop | grep -qP '\trefs/heads/develop$'; then
    git branch -f develop "origin/$BRANCH"
    git push -q -u origin develop
fi

if [ -f deploy/docker-compose.yml ]; then
    if grep -q 'build:' deploy/docker-compose.yml; then
        docker compose -f deploy/docker-compose.yml build --quiet
    fi
    docker compose -f deploy/docker-compose.yml up -d --force-recreate
    PUBLISHED=$(docker port "$PROJECT" 80 2>/dev/null || echo '?')
    echo "[homelab] Fertig: $PROJECT laeuft auf $PUBLISHED"
fi