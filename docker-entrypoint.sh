#!/bin/bash
set -e

# ── Parse DATABASE_URL (Render/Neon style) into DB_* vars ────────────────
# Accepts postgres://user:pass@host:port/dbname?sslmode=require
# Only fills variables that aren't already set, so explicit DB_HOST etc. still win.
if [ -n "$DATABASE_URL" ]; then
    eval "$(php -r '
        $url = getenv("DATABASE_URL");
        $p   = parse_url($url);
        if ($p === false) { fwrite(STDERR, "Invalid DATABASE_URL\n"); exit(1); }
        $q = [];
        if (!empty($p["query"])) { parse_str($p["query"], $q); }
        $exports = [
            "DB_CONNECTION" => (($p["scheme"] ?? "pgsql") === "mysql") ? "mysql" : "pgsql",
            "DB_HOST"       => $p["host"] ?? "",
            "DB_PORT"       => $p["port"] ?? "5432",
            "DB_DATABASE"   => isset($p["path"]) ? ltrim($p["path"], "/") : "",
            "DB_USERNAME"   => $p["user"] ?? "",
            "DB_PASSWORD"   => isset($p["pass"]) ? urldecode($p["pass"]) : "",
            "DB_SSLMODE"    => $q["sslmode"] ?? "require",
        ];
        foreach ($exports as $k => $v) {
            if (getenv($k) === false || getenv($k) === "") {
                echo "export $k=" . escapeshellarg($v) . "\n";
            }
        }
    ')"
fi

# Generate app key if not set or not a valid Laravel key (must start with base64:)
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
    echo "APP_KEY missing or invalid — generating..."
    php artisan key:generate --force
fi

# Apply the raw SQL schema directly via PHP (safe to re-run: uses IF NOT EXISTS)
php -r "
\$host = getenv('DB_HOST');
\$port = getenv('DB_PORT') ?: '5432';
\$db   = getenv('DB_DATABASE') ?: 'neondb';
\$user = getenv('DB_USERNAME');
\$pass = getenv('DB_PASSWORD');
\$ssl  = getenv('DB_SSLMODE') ?: 'require';

if (!\$host || !\$user) {
    echo \"ERROR: DB_HOST and DB_USERNAME must be set (or provide DATABASE_URL)\n\";
    exit(1);
}

\$dsn = \"pgsql:host=\$host;port=\$port;dbname=\$db;sslmode=\$ssl\";
\$pdo = new PDO(\$dsn, \$user, \$pass);
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo \"Applying database schema (IF NOT EXISTS)...\n\";
\$sql = file_get_contents('/app/database/schema.sql');
\$pdo->exec(\$sql);
echo \"Schema applied successfully.\n\";

/* ── Add room_type column if missing (handles pre-existing tables) ──── */
\$cols = \$pdo->query(\"SELECT column_name FROM information_schema.columns WHERE table_name = 'chat_rooms' AND column_name = 'room_type'\");
if (\$cols->rowCount() === 0) {
    echo \"Adding room_type column to chat_rooms...\n\";
    \$pdo->exec(\"ALTER TABLE chat_rooms ADD COLUMN room_type VARCHAR(10) NOT NULL DEFAULT 'dm'\");
    \$pdo->exec(\"UPDATE chat_rooms SET room_type = 'team' WHERE room_id IN (SELECT id FROM teams)\");
    \$pdo->exec(\"CREATE INDEX IF NOT EXISTS idx_chatrooms_room_type ON chat_rooms(room_type)\");
    echo \"room_type column added and back-filled.\n\";
}
"

# Seed only if the users table is empty (first deploy)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "First deploy detected — seeding database..."
    php artisan db:seed --force
fi

# Ensure public/storage symlink exists so uploaded files are publicly served
if [ ! -L /app/public/storage ]; then
    php artisan storage:link || true
fi

# Cache config for production performance; clear route cache (closure routes can't be cached)
php artisan config:cache
php artisan route:clear

# Start the server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
