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

cd "$REMOTE_PATH"
git fetch origin "$REMOTE_BRANCH"
git checkout "$REMOTE_BRANCH"
git pull --ff-only origin "$REMOTE_BRANCH"
docker-compose up -d --build
docker-compose exec -T app sh -c 'mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwx storage bootstrap/cache'
docker-compose exec -T app php artisan migrate --force
docker-compose exec -T app php artisan optimize
EOF