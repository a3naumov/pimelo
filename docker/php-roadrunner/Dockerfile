ARG ROADRUNNER_VERSION=2025.1.15
ARG PHP_VERSION=8.5.9
ARG PHP_EXTENSION_INSTALLER_VERSION=2.11.12

ARG COMPOSER_VERSION=2.10.2
FROM composer:${COMPOSER_VERSION} AS composer

FROM mlocati/php-extension-installer:${PHP_EXTENSION_INSTALLER_VERSION} AS php-extension-installer

FROM ghcr.io/roadrunner-server/roadrunner:${ROADRUNNER_VERSION} AS roadrunner

FROM php:${PHP_VERSION}-cli-alpine AS base

# Install php extensions
COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/install-php-extensions
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    zip \
    pcntl \
    sockets

# Install composer and RoadRunner
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

# Create non-root user and switch to it
ARG USER=appuser
RUN <<-EOF
	addgroup -S ${USER}
	adduser -S -D -G ${USER} ${USER}
	mkdir -p /app
	chown ${USER}:${USER} /app
EOF

WORKDIR /app

EXPOSE 8080

# The application provides the RoadRunner configuration and PHP worker.
CMD ["rr", "serve", "-c", "/app/.rr.yaml"]

# Check Symfony through the RoadRunner HTTP worker.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "-r", "exit(false === @file_get_contents('http://127.0.0.1:8080/', false, stream_context_create(['http' => ['timeout' => 3]])) ? 1 : 0);"]

FROM base AS dev

# Install and configure development extensions
RUN install-php-extensions \
    xdebug

RUN <<-EOF
	{
		echo 'opcache.enable=0'
		echo 'opcache.enable_cli=0'
	} > /usr/local/etc/php/conf.d/zz-opcache.ini

	{
		echo 'xdebug.mode=debug'
		echo 'xdebug.start_with_request=trigger'
		echo 'xdebug.client_host=host.docker.internal'
		echo 'xdebug.client_port=9003'
		echo 'xdebug.discover_client_host=false'
	} > /usr/local/etc/php/conf.d/zz-xdebug.ini
EOF

USER ${USER}

FROM base AS production

# Install and configure production extensions
RUN install-php-extensions opcache

RUN <<-EOF
	{
		echo 'opcache.enable=1'
		echo 'opcache.enable_cli=1'
		echo 'opcache.validate_timestamps=0'
		echo 'opcache.revalidate_freq=0'
	} > /usr/local/etc/php/conf.d/zz-opcache.ini
EOF

USER ${USER}
