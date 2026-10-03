.DEFAULT_GOAL := help

COMPOSE_BASE_FILES := compose.yaml \
	compose.pim.yaml \
	compose.frontend.yaml \
	compose.media-storage.yaml \
	compose.notifications.yaml \
	compose.search.yaml

COMPOSE_BASE = docker compose $(addprefix -f ,$(COMPOSE_BASE_FILES)) --profile '*'
COMPOSE_DEV = $(COMPOSE_BASE) -f compose.dev.yaml
COMPOSE_MONITORING = docker compose $(addprefix -f ,$(COMPOSE_BASE_FILES)) -f compose.monitoring.yaml --profile monitoring
MONITORING_SERVICES := monitoring-grafana monitoring-prometheus monitoring-loki \
	monitoring-alloy monitoring-telegraf monitoring-socket-proxy \
	monitoring-postgres-exporter monitoring-redis-exporter monitoring-kafka-exporter \
	monitoring-blackbox-exporter

.PHONY: help dev prod down monitoring-init monitoring monitoring-stop

help:
	@printf '%s\n' \
		'make dev   Build and start all services in development mode.' \
		'make prod  Build and start all services without development overrides.' \
		'make down  Stop and remove project containers and networks; keep volumes.' \
		'make monitoring-init  Prepare the PostgreSQL monitoring role (database must be running).' \
		'make monitoring       Start monitoring only (applications must already be running).' \
		'make monitoring-stop  Stop monitoring only; keep its containers and history.'

dev:
	$(COMPOSE_DEV) up -d --build

prod:
	$(COMPOSE_BASE) up -d --build

# Include development-only services when stopping either mode. Keep data volumes.
down:
	$(COMPOSE_DEV) -f compose.monitoring.yaml down

monitoring-init:
	$(COMPOSE_MONITORING) run --rm --no-deps monitoring-postgres-init

monitoring:
	$(COMPOSE_MONITORING) run --rm --no-deps monitoring-postgres-init /etc/monitoring/check-env.sh
	$(COMPOSE_MONITORING) up -d --no-deps $(MONITORING_SERVICES)

monitoring-stop:
	$(COMPOSE_MONITORING) stop $(MONITORING_SERVICES)
