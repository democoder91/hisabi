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

composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

npm ci
npm run build

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan storage:link --force
php artisan migrate --force
php artisan optimize
EOF