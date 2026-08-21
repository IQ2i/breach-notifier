#syntax=docker/dockerfile:1.4

FROM php:8.5-cli-alpine AS base

COPY --from=mlocati/php-extension-installer --link /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions \
        intl \
        opcache \
        pcntl \
        pdo_sqlite \
        zip \
    ;

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /srv/app

# ---------------------------------------------------------------------------
# dev: used by compose.yaml, code is bind-mounted, not copied into the image
# ---------------------------------------------------------------------------
FROM base AS dev

COPY --from=composer/composer:2-bin --link /composer /usr/bin/composer

RUN apk add --no-cache git \
    && install-php-extensions pcov \
    && sed -i 's/opcache.validate_timestamps=0/opcache.validate_timestamps=1/' /usr/local/etc/php/conf.d/opcache-recommended.ini

CMD ["php", "-a"]

# ---------------------------------------------------------------------------
# builder: installs dependencies and copies the application code once
# ---------------------------------------------------------------------------
FROM dev AS builder

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

# ---------------------------------------------------------------------------
# prod: self-contained image, meant to be run one-shot (e.g. from host cron)
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod

RUN addgroup -S app && adduser -S app -G app && chown app:app /srv/app
COPY --from=builder --chown=app:app /srv/app /srv/app

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

USER app

ENTRYPOINT ["entrypoint.sh"]
CMD ["bin/console", "app:breach:check"]
