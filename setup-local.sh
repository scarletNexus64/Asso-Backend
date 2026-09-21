#!/usr/bin/env bash
# Setup complet de l'environnement de developpement local (macOS / Homebrew).
# Idempotent : relancable sans risque. Ne touche jamais a une base autre que $DB_NAME.
set -euo pipefail

cd "$(dirname "$0")"

DB_NAME="${DB_NAME:-asso_local}"
DB_USER="${DB_USER:-$(whoami)}"

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }

say "Verification des prerequis"
for bin in php composer npm psql; do
  command -v "$bin" >/dev/null || { echo "ERREUR: '$bin' introuvable dans le PATH."; exit 1; }
done
pg_isready -q || { echo "ERREUR: PostgreSQL ne repond pas (brew services start postgresql@17)."; exit 1; }

say "Dependances PHP"
composer install --no-interaction

say "Fichier .env"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "  .env cree depuis .env.example — PENSEZ a y regler DB_* et les cles tierces."
else
  echo "  .env deja present, conserve."
fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --ansi

say "Arborescence storage (non versionnee par git)"
mkdir -p storage/framework/{views,cache/data,sessions,testing} \
         storage/logs storage/app/{public,private/firebase}
chmod -R 775 storage bootstrap/cache
[ -L public/storage ] || php artisan storage:link

say "Base de donnees '$DB_NAME'"
if psql -U "$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
  echo "  base deja existante, conservee."
else
  createdb -U "$DB_USER" "$DB_NAME"
  echo "  base creee."
fi
# Extensions requises par la recherche full-text (peut demander les droits superuser).
psql -U "$DB_USER" -d "$DB_NAME" -q \
  -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;" \
  -c "CREATE EXTENSION IF NOT EXISTS unaccent;"

say "Migrations"
php artisan migrate --force

say "Seeders (base)"
php artisan db:seed --force

# Jeu de donnees de demo : boutiques, produits, avis, packages.
# Desactivable avec SEED_DEMO=0 (base propre facon production).
# Ces seeders ne sont PAS idempotents (contrainte unique sur products.slug) :
# on ne les rejoue donc que si aucun produit n'existe encore.
if [ "${SEED_DEMO:-1}" = "1" ]; then
  PRODUCT_COUNT=$(psql -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT count(*) FROM products;" 2>/dev/null || echo 0)
  if [ "$PRODUCT_COUNT" -eq 0 ]; then
    say "Seeders de demo (produits, avis, packages)"
    php artisan db:seed --class=ProductSeeder --force
    php artisan db:seed --class=PackageSeeder --force
    php artisan db:seed --class=ProductReviewsSeeder --force
    php artisan db:seed --class=OrderTestSeeder --force
  else
    say "Seeders de demo ignores ($PRODUCT_COUNT produits deja presents)"
  fi
fi

say "Assets front"
npm install
npm run build

say "Nettoyage des caches"
php artisan optimize:clear

cat <<'MSG'

Setup termine.

  Demarrer :  composer run dev     (serveur + queue + logs + vite)
  ou :        php artisan serve    -> http://localhost:8000

  Admin      : http://localhost:8000/admin/login
               admin@asso.com / password
  API login  : POST /api/v1/auth/login-email

MSG
