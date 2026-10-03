# Pimelo gateway

Symfony 8.1 / PHP 8.5 / RoadRunner HTTP gateway. Caddy is the public entry point;
this container and downstream PHP services have no published host ports.
No authentication, gRPC, WebSocket or SSE forwarding is configured.

## Setup

Install dependencies with `composer install` in this directory. Start the desired
services from the repository root, for example:

```sh
docker compose --profile caddy --profile gateway --profile pim --profile frontend up -d --build
curl http://localhost/pim/
curl http://localhost/pim/web/products/
```

`GET /` inside gateway is its own healthcheck. Public service prefixes `/pim`,
`/search`, `/media-storage`, and `/notifications` are removed before forwarding.
Both `/pim` and `/pim/` target PIM's `/`. Unrecognized prefixes return JSON 404;
Caddy also sends the legacy `/web/*` path here so it cannot become SPA HTML.
Unmatched non-service Caddy routes remain frontend routes.

## Configuration

Override defaults in ignored `.env.local` (or real environment variables):

```dotenv
PIM_URL=http://pim:8080
SEARCH_URL=http://search:8080
MEDIA_STORAGE_URL=http://media-storage:8080
NOTIFICATIONS_URL=http://notifications:8080
UPSTREAM_TIMEOUT=10
UPSTREAM_MAX_DURATION=60
```

Upstream values are trusted operator configuration: use HTTP(S) origins without
paths, credentials, queries or fragments. Clients cannot select arbitrary hosts.
Idle timeout is 10 seconds; total request duration is capped at 60 seconds.
Redirects and failed requests are not followed or retried automatically.

The proxy forwards methods, raw query strings, payloads, status codes and
end-to-end headers. Form fields and uploads already parsed by RoadRunner are
rebuilt using Symfony Mime. Downloads stream without buffering the entire body.
Hop-by-hop headers and untrusted forwarding headers are removed. Upstream
redirects are rewritten to the public service prefix. Transport errors before
headers return JSON 502 (connection) or 504 (timeout); interrupted streams are
logged and terminated without appending JSON to a partially downloaded file.
As with any streaming proxy, an interruption after headers cannot change the
already-sent status code.

Caddy replaces client forwarding headers. Gateway trusts private proxy addresses;
keep it on the internal network and do not publish its port directly.

## CORS

NelmioCorsBundle runs only here, not in PIM. It applies to all four public service
prefixes, including upstream errors and gateway-generated 502/504 responses.
Preflight does not contact downstream services. Cookie credentials are disabled.

Development/test allow localhost and 127.0.0.1 on ports 5173/4173. Production
defaults to no allowed origins (`(?!)`). Configure a fully anchored regular
expression for cross-origin production clients, for example:

```dotenv
CORS_ALLOW_ORIGIN='^https://(app|admin)\.example\.com$'
```

Restart gateway workers after changing environment values. The frontend uses
`VITE_GATEWAY_URL` for Caddy's public origin and appends `/pim/web`.

## Checks

```sh
composer check
# Or from the repository root:
docker compose exec -T gateway composer check
```

This validates the Symfony container and runs PHPUnit (unit proxy tests and
functional routing/CORS tests). Upstreams are mocked: no database or other
application containers are needed. GitHub Actions runs the same command.
