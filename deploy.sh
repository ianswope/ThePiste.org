#!/usr/bin/env bash
# ThePiste deploy — run on the server: `cd /var/www/thepiste && ./deploy.sh`
# Pulls main from GitHub, rebuilds, migrates, recaches, reloads php-fpm.
set -e

cd /var/www/thepiste

echo ">> pull"
git fetch origin main
git reset --hard origin/main

echo ">> php deps"
COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader --no-dev --no-interaction

echo ">> assets"
npm ci
npm run build

echo ">> pre-migrate safety dump"
mkdir -p /var/backups/mysql
mysqldump --single-transaction --quick thepiste | gzip > "/var/backups/mysql/thepiste-predeploy-$(date +%Y%m%d-%H%M%S).sql.gz"

echo ">> migrate + cache"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ">> permissions + reload"
chown -R www-data:www-data storage bootstrap/cache
systemctl reload php8.4-fpm
# Gracefully reload the queue worker so it runs the new code instead of a
# stale in-memory copy (supervisor restarts it). No-op if none is running.
php artisan queue:restart

echo "Deployed thepiste.org @ $(git rev-parse --short HEAD)"
