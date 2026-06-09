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

echo ">> migrate + cache"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ">> permissions + reload"
chown -R www-data:www-data storage bootstrap/cache
systemctl reload php8.4-fpm

echo "Deployed thepiste.org @ $(git rev-parse --short HEAD)"
