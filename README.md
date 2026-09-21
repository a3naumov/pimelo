# pimelo
[pimelo](https://github.com/a3naumov/pimelo)

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
