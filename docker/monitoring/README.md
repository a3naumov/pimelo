# Monitoring

Optional, single-host monitoring for Docker Desktop and Linux. All services live
in `compose.monitoring.yaml`, use the `monitoring` profile, and have names such as
`${COMPOSE_PROJECT_NAME}-monitoring-grafana`. This is the existing Compose project,
not a separate Docker Desktop project or a high-availability deployment.

## Start and stop

1. Start applications normally with `make dev` or `make prod`. Monitoring never
   starts, recreates, or stops them automatically.
2. Add these settings to the Git-ignored root `.env`. Generate **different** random
   passwords, for example with `openssl rand -hex 24` twice. Do not commit passwords.

   ```dotenv
   SERVICE_MONITORING_GRAFANA_PORT=3000
   SERVICE_MONITORING_GRAFANA_ADMIN_USER=admin
   SERVICE_MONITORING_GRAFANA_ADMIN_PASSWORD=replace-with-a-random-password
   SERVICE_MONITORING_POSTGRES_PASSWORD=replace-with-another-random-password
   ```

3. With PostgreSQL already running, prepare its monitoring account and start:

   ```sh
   make monitoring-init
   make monitoring
   ```

4. Open **http://localhost:3000**, log in with the configured Grafana credentials,
   and open the **Pimelo** dashboard folder.

```sh
make monitoring-stop  # Stop only monitoring; keep containers, volumes and history
make monitoring       # Start it again without restarting applications
make down             # Remove all project containers/networks; KEEP data volumes
```

The monitoring file is intentionally not part of the default `COMPOSE_FILE` or
`make dev/prod`. The monitoring commands select their own file list. Do not use
`docker compose down` with a monitoring-only invocation: Docker Compose shares
project networks with the applications. Never use `down -v` unless you intend to
delete project data, including application databases.

After installing these changes into an already-running stack, restart the five
PHP services once to apply their RoadRunner configuration:

```sh
docker compose restart gateway pim search media-storage notifications
```

No database recreation or application migrations are required. `monitoring-init`
is idempotent: it creates/updates only the dedicated `pimelo_monitoring` login,
grants `pg_monitor` and access to the `postgres` database, and rejects an existing
privileged role with that name. It does not grant application table writes.
Run it again after rotating `SERVICE_MONITORING_POSTGRES_PASSWORD`, then run
`make monitoring` to apply the new exporter environment.

Grafana's admin environment values initialize **new** Grafana storage only.
Changing the env password does not reset an account already stored in its volume;
use Grafana's account settings or its documented admin password reset procedure.

## Components and dashboards

| Component | Responsibility |
| --- | --- |
| Grafana | Provisioned dashboards and Explore; only published monitoring port |
| Prometheus | Metrics storage and 15-second scrape interval |
| Loki | Log storage with filesystem-backed TSDB and retention compactor |
| Alloy | Docker stdout/stderr collection and persistent read positions |
| Telegraf | Docker CPU, memory, network, I/O and container state via Docker API |
| Socket proxy | Read-only Docker API allowlist for Alloy and Telegraf |
| PostgreSQL Exporter | Connections, transactions, locks and database sizes |
| Redis Exporter | Clients, memory, operations, cache hits/misses and evictions |
| Kafka Exporter | Brokers, topic partitions/offsets and consumer group lag |
| Blackbox Exporter | HTTP health probes independent of the frontend |

Dashboards and data sources are provisioned from this directory, not downloaded
at startup. Edit the JSON files to change dashboards; UI edits are disabled.

- **Overview:** HTTP health, PostgreSQL/Redis/Kafka availability, scrape targets
  and request rates. A successful exporter scrape alone does not prove the
  underlying database is healthy, so database-specific health metrics are shown.
- **HTTP and RoadRunner:** requests by HTTP status, p95 latency, server errors,
  queue length, ready workers and worker memory for all five PHP services.
- **Infrastructure:** Docker CPU/RAM/network/uptime and PostgreSQL, Redis and
  Kafka metrics. Missing topics or consumer groups display **No data**, not zero lag.
- **Logs:** project/service/stream filters, log volume and regular-expression
  message search. The `stream` label distinguishes stdout and stderr.

RoadRunner listens internally on `9180/metrics`. The `http_metrics` middleware
collects native HTTP metrics without application instrumentation or RPC. Existing
healthcheck traffic is included. Development debug pools create workers on demand,
so their ready-worker and memory gauges may be zero; use Docker container memory
in development. Blackbox probes `/` on every PHP service and `/healthcheck` on
gateway; HTTP 200 and a JSON `status: "ok"` value are required. It does not follow
redirects. These are internal reachability probes, not an external Internet check.

PIM writes dev logs to both its existing file and JSON stderr (info and above,
excluding event/Doctrine/console channels). Its test logging is unchanged. Other
services' existing stderr output and RoadRunner access logs are collected as-is.
Logs are not indexed by URLs, UUIDs or message text; container environment values
are not exported as metric labels. Existing application messages can still contain
sensitive data: restricting Grafana access is necessary, not automatic redaction.

OpenSearch, MinIO and Centrifugo have container metrics and Docker logs when their
profiles are running, but no dedicated application exporters in this iteration.
Kafka JMX/JVM metrics, distributed tracing and external alerts are not configured.
Stopped application profiles appear unavailable; monitoring does not enable them.

## Storage, networking and security

```dotenv
SERVICE_MONITORING_METRICS_RETENTION=15d
SERVICE_MONITORING_METRICS_MAX_SIZE=5GB
SERVICE_MONITORING_LOG_RETENTION=168h
SERVICE_MONITORING_DOCKER_SOCKET=/var/run/docker.sock
```

Prometheus deletes old blocks by time or size (whichever limit is reached first).
The size limit is not a hard filesystem quota: allow additional WAL/headroom.
Loki defaults to seven days. Use hours for its retention setting, at least `24h`.
Its compactor runs every ten minutes and physical deletion has a two-hour delay.
Alloy discards historical Docker lines outside that retention window before
shipping them. Time retention is not a disk-size quota; provision and monitor
sufficient disk space. Application Docker-log rotation remains separately managed.

Named volumes preserve Grafana accounts, Prometheus history, Loki chunks/indexes/
deletion markers, and Alloy positions across container recreation. Reapplying
configuration must not remove these volumes.

Only Grafana is bound to the host, on **127.0.0.1**. Use an SSH tunnel for remote
access; do not publish this setup directly to the Internet. Prometheus, Loki,
exporters and the Docker proxy have no host port mappings. Anonymous Grafana
access and signup are disabled. Loki has no internal authentication and is intended
only for this trusted network. Changing `COMPOSE_PROJECT_NAME` creates a different
Compose deployment and different monitoring volumes.

Only the proxy mounts the Docker socket; a read-only socket mount alone does not
prevent Docker API writes. The proxy additionally allows specific GET/HEAD paths
and accepts clients only from Alloy/Telegraf. All other methods and archive/exec
endpoints are denied. Container inspection can expose metadata to these trusted
collectors; the proxy is not a per-project authorization boundary. Project
isolation is enforced by Alloy discovery and Telegraf's Compose-label filters.
No host root filesystem mounts, privileged containers or macOS system metrics
are used. Docker Desktop metrics describe containers inside its Linux VM.

Missing monitoring passwords stop the monitoring commands with an explicit error;
they do not prevent `make dev`, `make prod` or `make down` from resolving config.

## Verification and troubleshooting

Use the literal container names below with your project's prefix in place of `pimelo`:

```sh
docker exec pimelo-monitoring-prometheus promtool check config /etc/prometheus/prometheus.yaml
docker exec pimelo-monitoring-alloy alloy validate /etc/alloy/config.alloy
docker exec pimelo-monitoring-loki /usr/bin/loki -config.file=/etc/loki/config.yaml -config.expand-env=true -verify-config
docker logs --tail 50 pimelo-monitoring-alloy
docker logs --tail 50 pimelo-monitoring-telegraf
```

In Grafana Explore, use Prometheus queries `up`, `probe_success`, `pg_up`,
`redis_up`, `kafka_brokers`, and `docker_container_cpu_usage_percent`. Use Loki
`{job="docker", project="pimelo"}` for logs. Allow a few scrape intervals after
startup; rate/p95 panels need multiple samples. For partial stacks, stopped
services are expected to be down. After changing configs, restart the affected
monitoring container; Grafana periodically reloads provisioned dashboard files.

The exporters query running infrastructure without creating application data.
No topic or consumer group is created just to populate Kafka panels. If PostgreSQL
shows down, check that `monitoring-init` ran and that its password matches the
exporter environment. If Docker collection fails, check the configured socket
path and proxy logs rather than granting unrestricted API access.

Pinned image manifests support Linux AMD64 and ARM64. This stack does not supply
host-level node metrics, HA, off-host backups, TLS termination, or notification
channels. Back up its named volumes according to the deployment's requirements.
