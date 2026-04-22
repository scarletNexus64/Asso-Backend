#!/bin/bash
set -e

PROJECT_DIR="/home/asso/Asso-Backend"
SUPERVISOR_CONF="$PROJECT_DIR/supervisor.conf"

echo "========================================="
echo "  ASSO Backend - Production Deployment"
echo "========================================="

cd "$PROJECT_DIR"

# ─── Step 1: Install dependencies ───
echo ""
echo "[1/6] Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# ─── Step 2: Clear config/route/view caches (no DB needed) ───
echo ""
echo "[2/6] Clearing caches..."
php artisan config:clear
php artisan view:clear
php artisan route:clear

# ─── Step 3: Run migrations ───
echo ""
echo "[3/6] Running database migrations..."
php artisan migrate --force

# ─── Step 4: Clear DB-based cache & optimize ───
echo ""
echo "[4/6] Optimizing for production..."
php artisan cache:clear 2>/dev/null || true
php artisan optimize
php artisan route:cache
php artisan view:cache

# ─── Step 5: Ensure storage is linked ───
echo ""
echo "[5/6] Linking storage..."
php artisan storage:link 2>/dev/null || true

# ─── Step 6: Verify services ───
echo ""
echo "[6/6] Verifying configuration..."
php artisan about 2>/dev/null || echo "Laravel version: $(php artisan --version)"

echo ""
echo "========================================="
echo "  Deployment complete!"
echo "========================================="
echo ""
echo "To start all services with Supervisor:"
echo "  sudo supervisord -c $SUPERVISOR_CONF"
echo ""
echo "To manage services:"
echo "  sudo supervisorctl -c $SUPERVISOR_CONF status"
echo "  sudo supervisorctl -c $SUPERVISOR_CONF restart asso:"
echo "  sudo supervisorctl -c $SUPERVISOR_CONF stop asso:"
echo ""
