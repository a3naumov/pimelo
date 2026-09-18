ARG FRANKENPHP_VERSION=1.12.6
ARG PHP_VERSION=8.5.9

ARG COMPOSER_VERSION=2.10.2
FROM composer:${COMPOSER_VERSION} AS composer

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-alpine AS base

# Install php extensions
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    zip

# Install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Create non-root user and switch to it
ARG USER=appuser
RUN <<-EOF
	addgroup -S ${USER}
	adduser -S -D -G ${USER} ${USER}

	# Remove default capability
	setcap -r /usr/local/bin/frankenphp

	# Give write access to /config/caddy and /data/caddy
	chown -R ${USER}:${USER} /config/caddy /data/caddy
EOF

WORKDIR /app

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# Disable https
ENV SERVER_NAME=":80"

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

ARG PHP_IDE_CONFIG_SERVER_NAME
ENV PHP_IDE_CONFIG="serverName=${PHP_IDE_CONFIG_SERVER_NAME}"

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

# Enable worker mode by default outside development builds
ENV FRANKENPHP_CONFIG="worker /app/public/index.php"
