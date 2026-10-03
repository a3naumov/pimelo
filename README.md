# pimelo
[pimelo](https://github.com/a3naumov/pimelo)

## Quick start

Install Docker with Compose and GNU Make. Before the first launch, copy
`.env.example` to `.env` if it does not already exist, and configure its values.
Install application dependencies separately; these commands manage Docker
services and do not run Composer or npm installs.

```sh
make dev   # Build and start all profiles with development overrides
make prod  # Build and start all profiles without development overrides
make down  # Stop and remove containers and networks, preserving data volumes
make help  # Show available commands (also the default for plain make)
```

The Makefile selects Compose files and enables all profiles explicitly,
regardless of `COMPOSE_FILE` and `COMPOSE_PROFILES` in `.env`. Development adds
`compose.dev.yaml`; production uses only the base and group configurations.
Both launch commands build images before starting containers in the background.

The two modes share the same project, containers and data volumes. Run
`make down` before switching modes so development-only services do not remain
running. This does not delete database or broker data.

`make prod` starts the current production Compose configuration, not a complete
deployment pipeline. The frontend container does not start Vite automatically,
and a production frontend build is not yet mounted into Caddy's `/srv` directory.

## Docker Compose groups

Keep `COMPOSE_FILE` in the root `.env` in sync with `.env.example`. Include
`compose.dev.yaml` last for development; omit it for production configuration.
The root `compose.yaml` contains Caddy, gateway, Kafka, and the shared PHP image
build definition, including gateway/Kafka networks and the Kafka data volume.

| Profile | Services | Network |
| --- | --- | --- |
| `pim` | pim, postgres, redis | `${COMPOSE_PROJECT_NAME}-pim` |
| `gateway` | gateway | `${COMPOSE_PROJECT_NAME}-gateway` |
| `kafka` | kafka | `${COMPOSE_PROJECT_NAME}-kafka` |
| `frontend` | frontend | `${COMPOSE_PROJECT_NAME}-frontend` |
| `notifications` | notifications, centrifugo | `${COMPOSE_PROJECT_NAME}-notifications` |
| `media-storage` | media-storage, minio | `${COMPOSE_PROJECT_NAME}-media-storage` |
| `search` | search, opensearch, opensearch-dashboards (dev only) | `${COMPOSE_PROJECT_NAME}-search` |
| `caddy` | caddy | gateway and frontend networks |

PIM, frontend, notifications, media-storage, and search are defined in their
`compose.<group>.yaml` files. Gateway and Kafka stay in `compose.yaml` with their
own profiles. Every service has a profile, so select the groups to start explicitly:

```sh
# PIM and its database/cache (no public HTTP entry point)
docker compose --profile pim up -d

# PIM with public HTTP access and frontend
docker compose --profile pim --profile gateway --profile caddy --profile frontend up -d

# All groups
docker compose --profile '*' up -d
```

Alternatively, set `COMPOSE_PROFILES=pim,gateway,caddy,frontend` in the root `.env` to use
plain `docker compose up -d`. Set `COMPOSE_PROFILES=*` to enable all groups.
With no profiles enabled and no explicit service names, there are no services
selected to start. Explicitly selecting a service does not start its whole group.

The `php-roadrunner` build service belongs to all five PHP application profiles,
so the shared base remains available when building any one application. It keeps
`scale: 0` and `network_mode: none`: no extra runtime container or network.

Applications keep their own infrastructure networks and also join `gateway` and
`kafka`. PostgreSQL/Redis remain on PIM's network; Kafka has its own profile and
network. Caddy joins `gateway` and `frontend`. None of the five PHP services
publishes a host port, including in development.

## HTTP entry point

The browser calls Caddy (default `http://localhost`), then Symfony/RoadRunner
gateway forwards HTTP to an allowlisted service. Authentication and gRPC are not
configured. Gateway handles CORS; individual services do not.

| Public path | Internal destination |
| --- | --- |
| `/pim/web/products/` | `pim:8080/web/products/` |
| `/search/` | `search:8080/` |
| `/media-storage/` | `media-storage:8080/` |
| `/notifications/` | `notifications:8080/` |

The old `/web/*` entry point returns a JSON 404. Application routes inside PIM
still use `/web/*`. The frontend config is `VITE_GATEWAY_URL=http://localhost`
(origin only). See [gateway](src/gateway/README.md), [PIM](src/pim/README.md), and
[frontend](src/frontend/README.md) for setup and checks.

Gateway starts independently of downstream availability. Start only the desired
profiles; requests to a stopped service return 502. Development Kafka remains
available to host tools on `localhost:9092`; applications use `kafka:9092`.

Changing these files does not reconnect already running containers. Run `up -d`
for the desired profiles to apply the new network configuration. Existing named
volumes and their data are preserved; do not use `down -v` during this change.

When upgrading from the old layout, update `.env` from `.env.example` (including
Compose files/profiles), rename the frontend env key, and install Composer
dependencies in `src/pim` and `src/gateway`. After starting PIM, remove the obsolete
container with `docker rm -f pimelo-backend` (adjust the project prefix if needed).
Do not remove volumes: `postgres_data` and `kafka_data` retain their previous names.

## License

Copyright 2026 Artem Naumov.

Pimelo is licensed under the [Apache License, Version 2.0](LICENSE).
Third-party dependencies remain under their respective licenses.
