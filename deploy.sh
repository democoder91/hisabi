#!/usr/bin/env bash

set -euo pipefail

SERVER_USER="mohamed"
SERVER_HOST="95.111.225.31"
REMOTE_PATH="/var/www/nexosoft"
REMOTE_BRANCH="main"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "$SCRIPT_DIR"

git push origin HEAD:"$REMOTE_BRANCH"

ssh "${SERVER_USER}@${SERVER_HOST}" <<EOF
set -euo pipefail

if command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD='docker-compose'
else
    COMPOSE_CMD='docker compose'
fi

cd "$REMOTE_PATH"
git fetch origin "$REMOTE_BRANCH"
git checkout "$REMOTE_BRANCH"
git pull --ff-only origin "$REMOTE_BRANCH"
$COMPOSE_CMD up -d --build
$COMPOSE_CMD exec -T app sh -c 'mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwx storage bootstrap/cache'
$COMPOSE_CMD exec -T app php artisan migrate --force
$COMPOSE_CMD exec -T app php artisan optimize
EOF