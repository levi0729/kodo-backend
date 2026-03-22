#!/bin/bash
set -e

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run the raw SQL schema directly via PHP (avoids Laravel migration transaction issues)
php -r "
\$url = getenv('DATABASE_URL');
\$host = getenv('DB_HOST');
\$port = getenv('DB_PORT') ?: '5432';
\$db   = getenv('DB_DATABASE') ?: 'neondb';
\$user = getenv('DB_USERNAME');
\$pass = getenv('DB_PASSWORD');
\$ssl  = getenv('DB_SSLMODE') ?: 'require';

if (\$host && \$user) {
    \$dsn = \"pgsql:host=\$host;port=\$port;dbname=\$db;sslmode=\$ssl\";
    \$pdo = new PDO(\$dsn, \$user, \$pass);
} else {
    echo 'ERROR: DB_HOST and DB_USERNAME must be set\n';
    exit(1);
}

\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if schema already exists (users table = marker)
\$exists = \$pdo->query(\"SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'users')\")->fetchColumn();
if (!\$exists) {
    echo \"Applying database schema...\n\";
    \$sql = file_get_contents('/app/database/schema.sql');
    \$pdo->exec(\$sql);
    echo \"Schema applied.\n\";
} else {
    echo \"Schema already exists, skipping.\n\";
}
"

# Run Laravel migrations (sessions, cache, jobs tables)
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
