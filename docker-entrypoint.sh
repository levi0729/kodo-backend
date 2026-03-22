#!/bin/bash
set -e

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force

# Seed only if the users table is empty (first deploy)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "First deploy detected — seeding database..."
    php artisan db:seed --force
fi

# Clear and cache config for production
php artisan config:clear
php artisan route:clear

# Start the server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
