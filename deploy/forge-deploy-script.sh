# Laravel Forge deploy script (paste into Forge → site → Deployments → Deploy Script).
# Zero-downtime-safe order: install, build, migrate, cache, then reload PHP-FPM and queues.
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --class=RolesAndPermissionsSeeder --force   # idempotent: keeps the 6 roles in sync
$FORGE_PHP artisan filament:optimize
$FORGE_PHP artisan optimize
$FORGE_PHP artisan queue:restart
