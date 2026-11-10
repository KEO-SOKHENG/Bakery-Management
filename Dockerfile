# Stage 1: Build frontend assets
FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build


# Stage 2: Production PHP runtime
FROM php:8.3-apache

# Install system dependencies, PostgreSQL driver, and GD for DomPDF
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl zip gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# PostgreSQL client tools (pg_dump / pg_restore) for Full Backup & Restore.
# PG_CLIENT_MAJOR is REQUIRED and has no default: it must equal the verified production PostgreSQL server
# major version (a newer client can write archives that older servers cannot restore, an older one cannot
# dump a newer server). Verify with `SHOW server_version;` first, then build with
#   docker build --build-arg PG_CLIENT_MAJOR=<major> .
# Debian's own package is older than current servers, so the official PostgreSQL (PGDG) repository is used.
ARG PG_CLIENT_MAJOR
RUN case "${PG_CLIENT_MAJOR}" in \
        ''|*[!0-9]*) echo "ERROR: build argument PG_CLIENT_MAJOR is required (digits only), e.g. --build-arg PG_CLIENT_MAJOR=<verified production major>" >&2; exit 1 ;; \
    esac \
    && apt-get update && apt-get install -y --no-install-recommends curl ca-certificates gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && . /etc/os-release \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-${PG_CLIENT_MAJOR} \
    && rm -rf /var/lib/apt/lists/* \
    && /usr/lib/postgresql/${PG_CLIENT_MAJOR}/bin/pg_dump --version \
    && /usr/lib/postgresql/${PG_CLIENT_MAJOR}/bin/pg_restore --version
ENV PG_DUMP_PATH=/usr/lib/postgresql/${PG_CLIENT_MAJOR}/bin/pg_dump \
    PG_RESTORE_PATH=/usr/lib/postgresql/${PG_CLIENT_MAJOR}/bin/pg_restore

# PHP limits needed to upload backup packages and to process media (the stock image allows only 2M uploads).
COPY docker/php-bakery.ini /usr/local/etc/php/conf.d/zz-bakery.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source (excluding files listed in .dockerignore)
COPY . .

# Install PHP production dependencies
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# Copy compiled frontend assets from assets stage
COPY --from=assets /app/public/build /var/www/html/public/build

# Configure Apache virtual host for Laravel
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

# Prepare storage, bootstrap cache and public storage symlink
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             storage/app/public \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan storage:link

# Copy entrypoint script for dynamic port binding and runtime setup
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

ENV LOG_CHANNEL=stderr

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
