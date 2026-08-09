#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

php artisan migrate --force
php artisan db:seed --class=MoedaSeeder --force
php artisan db:seed --class=NotificacaoSeeder --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache