#!/usr/bin/env bash
# setup.sh — Bootstrap Nexosoft as a flat (no-Docker) installation on Ubuntu 24.04.
#
# Usage: sudo bash setup.sh
#
# What this script does:
#   1. Installs PHP 8.4 + extensions, Composer, Node.js 20, Nginx, MySQL 8.0, Caddy
#   2. Configures PHP-FPM, Nginx, and Caddy from infra/ configs
#   3. Creates the MySQL database and user
#   4. Sets directory permissions using ACLs (both www-data and deploy user)
#   5. Installs app dependencies, builds assets, runs migrations

set -euo pipefail

APP_DIR="/var/www/nexosoft"
DEPLOY_USER="mohamed"
PHP_VERSION="8.4"
DB_NAME="hisabi"
DB_USER="hisabi"
DB_PASS="123456"

# ──────────────────────────────────────────────
# Pre-flight checks
# ──────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    echo "ERROR: Run as root — sudo bash setup.sh" >&2
    exit 1
fi

if [[ ! -d "$APP_DIR" ]]; then
    echo "ERROR: App directory $APP_DIR not found. Clone the repo first." >&2
    exit 1
fi

echo "==> Starting Nexosoft flat installation..."

# ──────────────────────────────────────────────
# System packages
# ──────────────────────────────────────────────
echo "==> Updating system packages..."
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq

DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    curl \
    unzip \
    zip \
    git \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    acl \
    lsb-release

# ──────────────────────────────────────────────
# PHP 8.4
# ──────────────────────────────────────────────
if ! dpkg -l 2>/dev/null | grep -q "php${PHP_VERSION}-fpm"; then
    echo "==> Adding ondrej/php PPA..."
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
    apt-get update -qq

    echo "==> Installing PHP ${PHP_VERSION} and extensions..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-opcache
else
    echo "==> PHP ${PHP_VERSION} already installed, skipping."
fi

# Ensure php / php-config alternatives point to 8.4
update-alternatives --set php /usr/bin/php${PHP_VERSION} 2>/dev/null || true
update-alternatives --set php-config /usr/bin/php-config${PHP_VERSION} 2>/dev/null || true

# ──────────────────────────────────────────────
# Composer
# ──────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
    echo "==> Installing Composer..."
    curl -sS https://getcomposer.org/installer \
        | php -- --quiet --install-dir=/usr/local/bin --filename=composer
else
    echo "==> Composer already installed, skipping."
fi

# ──────────────────────────────────────────────
# Node.js 20
# ──────────────────────────────────────────────
if ! command -v node &>/dev/null; then
    echo "==> Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nodejs
else
    echo "==> Node.js already installed ($(node --version)), skipping."
fi

# ──────────────────────────────────────────────
# Nginx
# ──────────────────────────────────────────────
if ! command -v nginx &>/dev/null; then
    echo "==> Installing Nginx..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx
else
    echo "==> Nginx already installed, skipping."
fi

# ──────────────────────────────────────────────
# MySQL 8.0
# ──────────────────────────────────────────────
if ! command -v mysql &>/dev/null; then
    echo "==> Installing MySQL 8.0..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server
else
    echo "==> MySQL already installed, skipping."
fi

# ──────────────────────────────────────────────
# Caddy
# ──────────────────────────────────────────────
if ! command -v caddy &>/dev/null; then
    echo "==> Installing Caddy..."
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list > /dev/null
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq caddy
else
    echo "==> Caddy already installed, skipping."
fi

# ──────────────────────────────────────────────
# PHP-FPM pool configuration
# ──────────────────────────────────────────────
echo "==> Configuring PHP-FPM..."
mkdir -p /var/log/php
chown www-data:www-data /var/log/php

cp "${APP_DIR}/infra/php/nexosoft.pool.conf" "/etc/php/${PHP_VERSION}/fpm/pool.d/nexosoft.conf"
cp "${APP_DIR}/infra/php/opcache.ini" "/etc/php/${PHP_VERSION}/mods-available/nexosoft-opcache.ini"
ln -sf "/etc/php/${PHP_VERSION}/mods-available/nexosoft-opcache.ini" \
    "/etc/php/${PHP_VERSION}/fpm/conf.d/20-nexosoft-opcache.ini"

# Disable the default www pool to avoid a blank FastCGI listener
if [[ -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" ]]; then
    mv "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" \
       "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf.disabled"
fi

# ──────────────────────────────────────────────
# Nginx site configuration
# ──────────────────────────────────────────────
echo "==> Configuring Nginx..."
cp "${APP_DIR}/infra/nginx/nexosoft.conf" /etc/nginx/sites-available/nexosoft
ln -sf /etc/nginx/sites-available/nexosoft /etc/nginx/sites-enabled/nexosoft
rm -f /etc/nginx/sites-enabled/default
nginx -t

# ──────────────────────────────────────────────
# Caddy configuration
# ──────────────────────────────────────────────
echo "==> Configuring Caddy..."
cp "${APP_DIR}/infra/caddy/Caddyfile" /etc/caddy/Caddyfile
caddy validate --config /etc/caddy/Caddyfile

# ──────────────────────────────────────────────
# MySQL database and user
# ──────────────────────────────────────────────
echo "==> Configuring MySQL..."
systemctl enable mysql
systemctl start mysql

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'  IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ──────────────────────────────────────────────
# Directory permissions
# ──────────────────────────────────────────────
echo "==> Setting directory permissions..."

# Deploy user needs to be in www-data group so Nginx can read files via group
usermod -aG www-data "${DEPLOY_USER}"

# Base: deploy user owns files, www-data group for Nginx traversal
chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"
find "${APP_DIR}" \
    -not -path "${APP_DIR}/.git/*" \
    -not -path "${APP_DIR}/node_modules/.bin/*" \
    -not -path "${APP_DIR}/vendor/bin/*" \
    -type f -exec chmod 640 {} +
find "${APP_DIR}" \
    -not -path "${APP_DIR}/.git/*" \
    -type d -exec chmod 750 {} +
chmod +x "${APP_DIR}/artisan"
# Restore execute bits on binary directories
chmod +x "${APP_DIR}/node_modules/.bin/"* 2>/dev/null || true
chmod +x "${APP_DIR}/vendor/bin/"* 2>/dev/null || true

# Public dir must be traversable by Nginx (www-data, via group r-x on 750 is fine,
# but static asset reads need group r on files — 640 is sufficient since Nginx is
# in www-data group — no change needed beyond what's already set above)

# Ensure writable runtime dirs exist
mkdir -p \
    "${APP_DIR}/storage/app/public" \
    "${APP_DIR}/storage/framework/cache/data" \
    "${APP_DIR}/storage/framework/sessions" \
    "${APP_DIR}/storage/framework/views" \
    "${APP_DIR}/storage/logs" \
    "${APP_DIR}/bootstrap/cache"

# Use POSIX ACLs so both www-data (PHP-FPM) and the deploy user can always
# read/write/execute inside storage/ and bootstrap/cache/, including new files.
setfacl -R -m \
    "u:www-data:rwx,u:${DEPLOY_USER}:rwx,d:u:www-data:rwx,d:u:${DEPLOY_USER}:rwx" \
    "${APP_DIR}/storage" \
    "${APP_DIR}/bootstrap/cache"

# ──────────────────────────────────────────────
# Sudoers — password-free service reloads for deploy user
# ──────────────────────────────────────────────
echo "==> Configuring sudoers..."
cat > /etc/sudoers.d/nexosoft <<SUDOERS
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload php${PHP_VERSION}-fpm
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl restart php${PHP_VERSION}-fpm
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx
SUDOERS
chmod 440 /etc/sudoers.d/nexosoft

# ──────────────────────────────────────────────
# Update .env for flat installation
# ──────────────────────────────────────────────
echo "==> Updating .env..."
if [[ -f "${APP_DIR}/.env" ]]; then
    sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' "${APP_DIR}/.env"
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' "${APP_DIR}/.env"
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' "${APP_DIR}/.env"
elif [[ -f "${APP_DIR}/.env.example" ]]; then
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' "${APP_DIR}/.env"
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' "${APP_DIR}/.env"
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' "${APP_DIR}/.env"
    sudo -H -u "${DEPLOY_USER}" php artisan key:generate
fi
chown "${DEPLOY_USER}:www-data" "${APP_DIR}/.env"
chmod 640 "${APP_DIR}/.env"

# ──────────────────────────────────────────────
# Data migration from Docker (optional)
# ──────────────────────────────────────────────
if command -v docker &>/dev/null && docker ps --format '{{.Names}}' 2>/dev/null | grep -q nexosoft-db; then
    echo ""
    echo "==> Docker MySQL detected. Migrating data..."
    docker exec nexosoft-db mysqldump \
        -u root -p"${DB_PASS}" \
        --no-tablespaces \
        "${DB_NAME}" > /tmp/nexosoft-db-migration.sql
    mysql -u root "${DB_NAME}" < /tmp/nexosoft-db-migration.sql
    rm /tmp/nexosoft-db-migration.sql
    echo "    Data migrated from Docker MySQL to local MySQL."
else
    echo ""
    echo "    NOTE: No running Docker MySQL found. If you have existing data, migrate manually:"
    echo "      docker exec nexosoft-db mysqldump -u root -p${DB_PASS} ${DB_NAME} > /tmp/db.sql"
    echo "      mysql -u root ${DB_NAME} < /tmp/db.sql"
fi

# ──────────────────────────────────────────────
# Install app dependencies and build assets
# ──────────────────────────────────────────────
echo "==> Installing PHP dependencies..."
cd "${APP_DIR}"
sudo -H -u "${DEPLOY_USER}" composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

echo "==> Building frontend assets..."
sudo -H -u "${DEPLOY_USER}" npm ci
sudo -H -u "${DEPLOY_USER}" npm run build

echo "==> Running artisan setup..."
sudo -H -u "${DEPLOY_USER}" php artisan storage:link --force
sudo -H -u "${DEPLOY_USER}" php artisan migrate --force
sudo -H -u "${DEPLOY_USER}" php artisan optimize

# ──────────────────────────────────────────────
# Enable and start all services
# ──────────────────────────────────────────────
echo "==> Starting services..."
systemctl enable php${PHP_VERSION}-fpm nginx caddy mysql
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx
systemctl restart caddy
systemctl restart mysql

echo ""
echo "=========================================="
echo " Nexosoft flat installation complete!"
echo "=========================================="
echo ""
printf "  %-12s %s\n" "php-fpm:"  "$(systemctl is-active php${PHP_VERSION}-fpm)"
printf "  %-12s %s\n" "nginx:"    "$(systemctl is-active nginx)"
printf "  %-12s %s\n" "caddy:"    "$(systemctl is-active caddy)"
printf "  %-12s %s\n" "mysql:"    "$(systemctl is-active mysql)"
echo ""
echo "  App: https://nexosoft.online"
echo ""
echo "  IMPORTANT: Log out and back in for the www-data group"
echo "  membership to take effect in your shell session."
echo ""
echo "  To deploy updates: ./deploy.sh"
