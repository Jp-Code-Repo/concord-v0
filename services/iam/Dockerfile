# IAM service image - the asqueue pattern.
#
# Dependencies are installed INTO the image, so vendor/ never has to be read through
# the Windows bind mount (that traversal was ~156x slower than the Linux filesystem:
# ~28 s vs ~0.18 s). docker-compose.yml then mounts the source code over /var/www and
# masks /var/www/vendor with a volume, so the image's vendor/ is what PHP actually uses.
#
# Build the shared base first:  docker compose build php-base
FROM concord/php:8.3

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www

# 1) Dependency layer: rebuilt only when composer.json / composer.lock change.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# 2) Application code (.dockerignore keeps host vendor/, .env and caches out).
COPY . .

# 3) Optimized autoloader. Classes added later (via the bind mount) still resolve:
#    anything missing from the class map falls back to PSR-4.
RUN composer dump-autoload --optimize --no-scripts \
 && mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions \
             storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
