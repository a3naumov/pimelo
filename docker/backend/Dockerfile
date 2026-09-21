# Compose provides the matching dev or production runtime as a build context.
FROM php_roadrunner AS base

# Check the backend health endpoint rather than the shared runtime default.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "-r", "exit(false === @file_get_contents('http://127.0.0.1:8080/web/healthcheck', false, stream_context_create(['http' => ['timeout' => 3]])) ? 1 : 0);"]

FROM base AS dev

ARG PHP_IDE_CONFIG_SERVER_NAME
ENV PHP_IDE_CONFIG="serverName=${PHP_IDE_CONFIG_SERVER_NAME}"

FROM base AS production
