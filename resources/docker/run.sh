#!/bin/bash

set -e

first_time_check() {
    if [ ! -f /var/www/cocktails/.env ]; then
        cp .env.dist .env

        php artisan key:generate
        php artisan storage:link

        if [ ! -f /var/www/cocktails/storage/bar-assistant/database.sqlite ]; then
            echo "Database not found, creating a new database..."
            touch /var/www/cocktails/storage/bar-assistant/database.sqlite
            php artisan migrate --force
            php artisan bar:open
        else
            echo "Database already exists, running migrations..."
            php artisan migrate --force
        fi
    fi
}

start_system() {
    mkdir -p /var/www/cocktails/storage/bar-assistant/uploads/{cocktails,ingredients,temp}
    first_time_check

    php artisan bar:refresh-search

    echo "Adding routes and config to cache..."

    php artisan config:cache
    php artisan route:cache

    echo "!***************************!"
    echo "!    Application ready      !"
    echo "!***************************!"
}

start_system