.DEFAULT_GOAL := help

COMPOSE_BASE_FILES := compose.yaml \
	compose.backend.yaml \
	compose.frontend.yaml \
	compose.media-storage.yaml \
	compose.notifications.yaml \
	compose.search.yaml

COMPOSE_BASE = docker compose $(addprefix -f ,$(COMPOSE_BASE_FILES)) --profile '*'
COMPOSE_DEV = $(COMPOSE_BASE) -f compose.dev.yaml

.PHONY: help dev prod down

help:
	@printf '%s\n' \
		'make dev   Build and start all services in development mode.' \
		'make prod  Build and start all services without development overrides.' \
		'make down  Stop and remove project containers and networks; keep volumes.'

dev:
	$(COMPOSE_DEV) up -d --build

prod:
	$(COMPOSE_BASE) up -d --build

# Include development-only services when stopping either mode. Keep data volumes.
down:
	$(COMPOSE_DEV) down
