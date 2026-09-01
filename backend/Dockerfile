FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git libzip-dev unzip \
    && docker-php-ext-install bcmath pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts

COPY . .
COPY docker/entrypoint.sh /usr/local/bin/electoflow-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/electoflow-entrypoint \
    && chmod +x /usr/local/bin/electoflow-entrypoint \
    && composer dump-autoload --optimize

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/electoflow-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
