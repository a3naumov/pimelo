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
The root `compose.yaml` contains Caddy and the shared PHP image build definition.

| Profile | Services | Network |
| --- | --- | --- |
| `backend` | backend, postgres, redis, kafka | `${COMPOSE_PROJECT_NAME}-backend` |
| `frontend` | frontend | `${COMPOSE_PROJECT_NAME}-frontend` |
| `notifications` | notifications, centrifugo | `${COMPOSE_PROJECT_NAME}-notifications` |
| `media-storage` | media-storage, minio | `${COMPOSE_PROJECT_NAME}-media-storage` |
| `search` | search, opensearch, opensearch-dashboards (dev only) | `${COMPOSE_PROJECT_NAME}-search` |
| `caddy` | caddy | `${COMPOSE_PROJECT_NAME}-caddy` |

Each application group is defined in `compose.<group>.yaml`. Every service has a
profile, so select the groups to start explicitly:

```sh
# Backend and its infrastructure
docker compose --profile backend up -d

# Backend and frontend
docker compose --profile backend --profile frontend up -d

# All groups
docker compose --profile '*' up -d
```

Alternatively, set `COMPOSE_PROFILES=backend,frontend` in the root `.env` to use
plain `docker compose up -d`. Set `COMPOSE_PROFILES=*` to enable all groups.
With no profiles enabled and no explicit service names, there are no services
selected to start. Explicitly selecting a service does not start its whole group.

The `php-roadrunner` build service belongs to all four PHP application profiles,
so the shared base remains available when building any one application. It keeps
`scale: 0` and `network_mode: none`: no extra runtime container or network.

Networks are separated by group, with no cross-group connections configured yet.
Caddy's existing proxy targets are retained, but it cannot reach backend or
frontend until those connections are configured. Use the published development
ports directly in the meantime. Published host ports remain unchanged; network
separation does not restrict access through those host ports.

Changing these files does not reconnect already running containers. Run `up -d`
for the desired profiles to apply the new network configuration. Existing named
volumes and their data are preserved; do not use `down -v` during this change.
