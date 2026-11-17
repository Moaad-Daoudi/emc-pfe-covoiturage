#!/bin/bash
set -e

echo "[SafarCar Entrypoint] Initializing SafarCar Platform container..."

# 1. Wait for MySQL PDO connection
until php -r "try { new PDO('mysql:host=database;dbname=covoiturage', 'covoit', 'secret'); exit(0); } catch (\Throwable \$e) { exit(1); }"; do
    echo "[SafarCar Entrypoint] Waiting for MySQL database..."
    sleep 2
done

echo "[SafarCar Entrypoint] Database is reachable."

# 2. Run database migrations safely
echo "[SafarCar Entrypoint] Executing Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

# 3. Seed data automatically if cities table is empty
CITY_COUNT=$(php -r "try { \$pdo = new PDO('mysql:host=database;dbname=covoiturage', 'covoit', 'secret'); \$stmt = \$pdo->query('SELECT COUNT(*) FROM city'); echo (int) \$stmt->fetchColumn(); } catch (\Throwable \$e) { echo 0; }")

if [ "$CITY_COUNT" -eq 0 ]; then
    echo "[SafarCar Entrypoint] Fresh database detected (0 cities). Loading Moroccan seed fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction
    echo "[SafarCar Entrypoint] Fixtures loaded successfully."
else
    echo "[SafarCar Entrypoint] Database already seeded ($CITY_COUNT cities present)."
fi

# 4. Clear/warmup cache in background or ensure writable var
mkdir -p /var/www/html/var/cache /var/www/html/var/log
chmod -R 777 /var/www/html/var/cache /var/www/html/var/log 2>/dev/null || true

echo "[SafarCar Entrypoint] Ready! Launching application server ($@)..."
exec "$@"
