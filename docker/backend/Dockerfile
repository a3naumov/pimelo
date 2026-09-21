# Compose provides the matching dev or production runtime as a build context.
FROM php_roadrunner AS base

FROM base AS dev

ARG PHP_IDE_CONFIG_SERVER_NAME
ENV PHP_IDE_CONFIG="serverName=${PHP_IDE_CONFIG_SERVER_NAME}"

FROM base AS production
