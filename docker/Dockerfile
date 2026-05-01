# =============================================================================
# Heratio — single-image PHP 8.3 + nginx + Laravel container
#
# Mirrors what bin/install does for a metal install, but containerised so the
# whole stack can be brought up with `docker compose up`. Composer + npm
# happen at build time; bin/install (DB schema + seeds + admin + ES indices +
# storage paths) runs at first boot via /docker/init.sh and is idempotent —
# safe to re-run on every container start.
#
# @copyright  Johan Pieterse / Plain Sailing
# @license    AGPL-3.0-or-later
# =============================================================================

FROM php:8.3-fpm-bookworm AS php-base

# --- system deps for Laravel + AtoM-derived schema + ES + IIIF helpers ------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates \
        nginx supervisor \
        libxml2-dev libxslt1-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev \
        libfreetype6-dev libonig-dev libicu-dev \
        libpq-dev default-mysql-client \
        ghostscript poppler-utils libimage-exiftool-perl \
        gosu \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli mbstring xml xsl zip gd intl bcmath opcache exif \
 && pecl install redis \
 && docker-php-ext-enable redis

# --- composer + node ---------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && rm -rf /var/lib/apt/lists/*

# --- app ---------------------------------------------------------------------
WORKDIR /var/www/heratio

# Copy lockfiles first so subsequent layers cache when only source changes
COPY composer.json composer.lock ./
COPY packages/ ./packages/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json package-lock.json* ./
RUN npm ci --silent

# Now the rest of the source
COPY . .
RUN npm run build --silent \
 && composer run-script post-autoload-dump --no-interaction || true \
 && chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true \
 && chmod -R 0775 storage bootstrap/cache 2>/dev/null || true

# --- runtime configs ---------------------------------------------------------
# ── Build-time AI-leak guard ─────────────────────────────────────────────────
# Per feedback_ai_remote_only.md: Heratio is an AI client, never a host.
# This step fails the build if any model weights / AI binaries snuck through
# .dockerignore. The published image is public on ghcr.io; we never want
# to ship model weights inside it (license risk + multi-GB bloat + wrong
# architectural signal).
RUN ! find /var/www/heratio \
        \( -name '*.safetensors' -o -name '*.gguf' -o -name '*.ggml' \
           -o -name '*.pt' -o -name '*.pth' -o -name '*.onnx' \
           -o -name '*.h5' -o -name '*.ckpt' -o -name '*.tflite' \
           -o -path '*/models/*' -o -path '*/.cache/huggingface/*' \
           -o -path '*/.cache/ollama/*' -o -path '*/llama.cpp/*' \) \
        -type f -print -quit | grep -q .  \
    || (echo "BUILD ABORTED: AI model artifact detected in build context (see .dockerignore)" && exit 1)

COPY docker/nginx.conf      /etc/nginx/sites-available/heratio
COPY docker/php-fpm.conf    /usr/local/etc/php-fpm.d/zz-heratio.conf
COPY docker/php.ini         /usr/local/etc/php/conf.d/zz-heratio.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/heratio.conf
COPY docker/init.sh          /usr/local/bin/heratio-init
COPY docker/entrypoint.sh    /usr/local/bin/heratio-entrypoint

RUN ln -sf /etc/nginx/sites-available/heratio /etc/nginx/sites-enabled/heratio \
 && rm -f /etc/nginx/sites-enabled/default \
 && chmod +x /usr/local/bin/heratio-init /usr/local/bin/heratio-entrypoint

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/heratio-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
