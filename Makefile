# =============================================================================
# Payment Gateway — Makefile
# =============================================================================
# Usage: make <target>
#        make help        — list all targets with descriptions
# =============================================================================

# ─── Configuration ────────────────────────────────────────────────────────────

DOCKER_COMPOSE  := docker compose
APP_SERVICE     := app
HORIZON_SERVICE := horizon

# Exec shorthand: run a command inside the app container as www-data
DC_EXEC         := $(DOCKER_COMPOSE) exec -u www-data $(APP_SERVICE)
DC_EXEC_TTY     := $(DOCKER_COMPOSE) exec $(APP_SERVICE)

PHP             := $(DC_EXEC) php
ARTISAN         := $(DC_EXEC) php artisan
COMPOSER        := $(DC_EXEC) composer

# Frontend (runs locally via npm in ./frontend)
NPM_FRONTEND    := npm --prefix frontend

# Colors (ANSI)
RESET  := \033[0m
BOLD   := \033[1m
GREEN  := \033[32m
YELLOW := \033[33m
CYAN   := \033[36m
RED    := \033[31m
GRAY   := \033[90m

# ─── Phony targets ────────────────────────────────────────────────────────────

.PHONY: help \
        up up-frontend down restart build rebuild logs ps \
        prod-up prod-down staging-up staging-down ci-up ci-down ci-test \
        monitoring-up monitoring-down prod-monitoring-up prod-monitoring-down staging-monitoring-up \
        telescope-install \
        install setup first-run \
        composer-install composer-update composer-dump \
        npm-install npm-build npm-dev \
        migrate migrate-fresh migrate-rollback migrate-status seed \
        cache-clear config-clear route-clear view-clear optimize \
        tinker shell shell-root \
        test test-unit test-feature test-coverage \
        lint lint-fix analyse mutation \
        reconcile prune \
        k6-create k6-list k6-webhook \
        helm-deps helm-lint helm-template \
        k8s-staging k8s-prod k8s-status k8s-rollback \
        horizon horizon-terminate horizon-pause horizon-continue horizon-status \
        queue-flush queue-retry-all queue-failed \
        storage-link \
        key-generate \
        env

# ─── Default ──────────────────────────────────────────────────────────────────

.DEFAULT_GOAL := help

# ─── Help ─────────────────────────────────────────────────────────────────────

help: ## Show this help message
	@printf "\n$(BOLD)$(CYAN)Payment Gateway$(RESET) — available targets:\n\n"
	@awk 'BEGIN { FS = ":.*##"; section="" } \
	    /^## / { printf "\n$(BOLD)$(YELLOW)%s$(RESET)\n", substr($$0, 4); next } \
	    /^[a-zA-Z_-]+:.*##/ { printf "  $(GREEN)%-22s$(RESET) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@printf "\n"

# =============================================================================
## Docker
# =============================================================================

up: ## Start backend containers in detached mode (API only)
	@printf "$(CYAN)Starting backend containers...$(RESET)\n"
	@$(DOCKER_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Done.$(RESET) API: http://localhost:$${APP_PORT:-8000}\n"

up-frontend: ## Start all containers including the Vue frontend (profile: frontend)
	@printf "$(CYAN)Starting all containers (with frontend)...$(RESET)\n"
	@$(DOCKER_COMPOSE) --profile frontend up -d --remove-orphans
	@printf "$(GREEN)Done.$(RESET) API: http://localhost:$${APP_PORT:-8000}  Frontend: http://localhost:$${FRONTEND_PORT:-3080}\n"

down: ## Stop and remove containers
	@printf "$(CYAN)Stopping containers...$(RESET)\n"
	@$(DOCKER_COMPOSE) down
	@printf "$(GREEN)Done.$(RESET)\n"

restart: ## Restart all containers
	@$(DOCKER_COMPOSE) restart

build: ## Build images (without cache if NOCACHE=1)
	@printf "$(CYAN)Building images...$(RESET)\n"
	@if [ "$(NOCACHE)" = "1" ]; then \
		$(DOCKER_COMPOSE) build --no-cache; \
	else \
		$(DOCKER_COMPOSE) build; \
	fi
	@printf "$(GREEN)Done.$(RESET)\n"

rebuild: ## Full rebuild: down → build --no-cache → up
	@$(MAKE) down
	@$(MAKE) build NOCACHE=1
	@$(MAKE) up

logs: ## Tail logs (SERVICE=<name> to filter, e.g. make logs SERVICE=horizon)
	@$(DOCKER_COMPOSE) logs -f $(SERVICE)

ps: ## Show running containers and their status
	@$(DOCKER_COMPOSE) ps

# =============================================================================
## Environments
# =============================================================================

PROD_COMPOSE    := docker compose -f docker-compose.yml -f docker-compose.prod.yml
STAGING_COMPOSE := docker compose -f docker-compose.yml -f docker-compose.staging.yml
CI_COMPOSE      := docker compose -f docker-compose.yml -f docker-compose.ci.yml

prod-up: ## Start production environment (no override.yml)
	@printf "$(CYAN)Starting production containers...$(RESET)\n"
	@$(PROD_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Production up.$(RESET)\n"

prod-down: ## Stop production environment
	@$(PROD_COMPOSE) down

prod-build: ## Build production images (target: prod / prod-worker)
	@$(PROD_COMPOSE) build $(if $(NOCACHE),--no-cache)

staging-up: ## Start staging environment
	@printf "$(CYAN)Starting staging containers...$(RESET)\n"
	@$(STAGING_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Staging up. API: http://localhost:$${APP_PORT:-8000}$(RESET)\n"

staging-down: ## Stop staging environment
	@$(STAGING_COMPOSE) down

ci-up: ## Start CI test environment (minimal, sync queue)
	@printf "$(CYAN)Starting CI containers...$(RESET)\n"
	@$(CI_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)CI containers ready.$(RESET)\n"

ci-down: ## Stop CI test environment
	@$(CI_COMPOSE) down

ci-test: ## Run full test suite inside CI environment
	@printf "$(CYAN)Running tests in CI environment...$(RESET)\n"
	@$(CI_COMPOSE) exec app php artisan test --colors=always

MONITORING_COMPOSE         := docker compose -f docker-compose.yml -f docker-compose.monitoring.yml
PROD_MONITORING_COMPOSE    := docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.monitoring.yml
STAGING_MONITORING_COMPOSE := docker compose -f docker-compose.yml -f docker-compose.staging.yml -f docker-compose.monitoring.yml

monitoring-up: ## Start dev + full monitoring stack (Prometheus + Grafana + ELK)
	@printf "$(CYAN)Starting monitoring stack...$(RESET)\n"
	@$(MONITORING_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Monitoring up.$(RESET) Grafana: http://localhost:3000  Kibana: http://localhost:5601\n"

monitoring-down: ## Stop monitoring stack
	@$(MONITORING_COMPOSE) down

prod-monitoring-up: ## Start production + full monitoring stack
	@printf "$(CYAN)Starting production + monitoring...$(RESET)\n"
	@$(PROD_MONITORING_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Done.$(RESET)\n"

prod-monitoring-down: ## Stop production + monitoring stack
	@$(PROD_MONITORING_COMPOSE) down

staging-monitoring-up: ## Start staging + full monitoring stack
	@printf "$(CYAN)Starting staging + monitoring...$(RESET)\n"
	@$(STAGING_MONITORING_COMPOSE) up -d --remove-orphans
	@printf "$(GREEN)Done.$(RESET)\n"

telescope-install: ## Install Laravel Telescope (run once after composer install)
	@printf "$(CYAN)Installing Telescope...$(RESET)\n"
	@$(DC_EXEC) php artisan telescope:install
	@$(DC_EXEC) php artisan migrate --force
	@printf "$(GREEN)Telescope ready: http://localhost:$${APP_PORT:-8000}/telescope$(RESET)\n"

# =============================================================================
## Setup
# =============================================================================

first-run: ## Full first-time setup: build → up → install → key → migrate → storage-link
	@$(MAKE) build
	@$(MAKE) up
	@printf "$(CYAN)Waiting for services to be ready...$(RESET)\n"
	@sleep 3
	@$(MAKE) install
	@$(MAKE) key-generate
	@$(MAKE) storage-link
	@$(MAKE) migrate
	@printf "\n$(BOLD)$(GREEN)Setup complete!$(RESET)\n"
	@printf "  App:     $(CYAN)http://localhost:$${APP_PORT:-8000}$(RESET)\n"
	@printf "  Horizon: $(CYAN)http://localhost:$${APP_PORT:-8000}/horizon$(RESET)\n"
	@printf "  Adminer: $(CYAN)http://localhost:$${ADMINER_PORT:-8080}$(RESET)\n\n"

setup: ## Alias for first-run
	@$(MAKE) first-run

install: ## Install all dependencies (composer + npm)
	@$(MAKE) composer-install
	@$(MAKE) npm-install

key-generate: ## Generate application key
	@$(ARTISAN) key:generate --no-interaction --force

storage-link: ## Create storage symlink
	@$(ARTISAN) storage:link --force 2>/dev/null || true

env: ## Copy .env.example → .env if .env does not exist
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		printf "$(GREEN).env created from .env.example$(RESET)\n"; \
	else \
		printf "$(YELLOW).env already exists — skipped$(RESET)\n"; \
	fi

# =============================================================================
## Dependencies
# =============================================================================

composer-install: ## Install PHP dependencies
	@printf "$(CYAN)Installing PHP dependencies...$(RESET)\n"
	@$(COMPOSER) install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update PHP dependencies
	@$(COMPOSER) update --no-interaction --prefer-dist

composer-dump: ## Regenerate autoloader
	@$(COMPOSER) dump-autoload --optimize

npm-install: ## Install frontend Node dependencies
	@printf "$(CYAN)Installing frontend dependencies...$(RESET)\n"
	@$(NPM_FRONTEND) install

npm-build: ## Build frontend assets for production
	@$(NPM_FRONTEND) run build

npm-dev: ## Start Vite dev server locally (foreground)
	@$(NPM_FRONTEND) run dev

# =============================================================================
## Database
# =============================================================================

migrate: ## Run pending migrations
	@$(ARTISAN) migrate --no-interaction --force

migrate-fresh: ## Drop all tables and re-run all migrations (WARNING: destructive)
	@printf "$(RED)This will DESTROY all data. Confirm? [y/N] $(RESET)"; \
	read ans; [ "$$ans" = "y" ] || { printf "$(YELLOW)Aborted.$(RESET)\n"; exit 0; }
	@$(ARTISAN) migrate:fresh --no-interaction --force

migrate-rollback: ## Rollback the last migration batch (STEP=N to rollback N batches)
	@$(ARTISAN) migrate:rollback --step=$(or $(STEP),1) --no-interaction --force

migrate-status: ## Show the status of each migration
	@$(ARTISAN) migrate:status

seed: ## Run database seeders
	@$(ARTISAN) db:seed --no-interaction --force

# =============================================================================
## Cache & Optimization
# =============================================================================

cache-clear: ## Clear all application caches
	@$(ARTISAN) cache:clear
	@$(ARTISAN) config:clear
	@$(ARTISAN) route:clear
	@$(ARTISAN) view:clear
	@printf "$(GREEN)All caches cleared.$(RESET)\n"

config-clear: ## Clear only config cache
	@$(ARTISAN) config:clear

route-clear: ## Clear only route cache
	@$(ARTISAN) route:clear

view-clear: ## Clear only compiled view cache
	@$(ARTISAN) view:clear

optimize: ## Cache config, routes, and views for production
	@$(ARTISAN) optimize
	@printf "$(GREEN)Optimized.$(RESET)\n"

# =============================================================================
## Testing
# =============================================================================

test: ## Run full test suite
	@printf "$(CYAN)Running tests...$(RESET)\n"
	@$(DC_EXEC) php artisan config:clear --ansi 2>/dev/null || true
	@$(DC_EXEC) php artisan test --colors=always

test-unit: ## Run unit tests only
	@$(DC_EXEC) php artisan test --testsuite=Unit --colors=always

test-feature: ## Run feature tests only
	@$(DC_EXEC) php artisan test --testsuite=Feature --colors=always

test-coverage: ## Run tests with HTML coverage report (outputs to coverage/)
	@printf "$(CYAN)Running tests with coverage...$(RESET)\n"
	@$(DC_EXEC) php -d xdebug.mode=coverage artisan test \
		--coverage-html=coverage \
		--colors=always
	@printf "$(GREEN)Coverage report: coverage/index.html$(RESET)\n"

# =============================================================================
## Code Quality
# =============================================================================

lint: ## Check code style with Laravel Pint (dry-run)
	@printf "$(CYAN)Checking code style...$(RESET)\n"
	@$(DC_EXEC) ./vendor/bin/pint --test

lint-fix: ## Fix code style with Laravel Pint
	@printf "$(CYAN)Fixing code style...$(RESET)\n"
	@$(DC_EXEC) ./vendor/bin/pint
	@printf "$(GREEN)Done.$(RESET)\n"

analyse: ## Run static analysis with Larastan (PHPStan)
	@printf "$(CYAN)Running static analysis...$(RESET)\n"
	@$(DC_EXEC) ./vendor/bin/phpstan analyse --memory-limit=512M
	@printf "$(GREEN)Done.$(RESET)\n"

mutation: ## Run mutation tests with Infection PHP (Domain + Application layers)
	@printf "$(CYAN)Running mutation tests...$(RESET)\n"
	@$(DC_EXEC) php -d xdebug.mode=coverage vendor/bin/infection \
		--threads=4 \
		--show-mutations \
		--no-interaction
	@printf "$(GREEN)Report: storage/logs/infection.log$(RESET)\n"

# =============================================================================
## Horizon & Queue
# =============================================================================

horizon: ## Start Horizon in the foreground (for local dev outside Docker)
	@$(ARTISAN) horizon

horizon-terminate: ## Gracefully terminate Horizon (triggers restart in Docker)
	@$(ARTISAN) horizon:terminate
	@printf "$(GREEN)Horizon will restart shortly.$(RESET)\n"

horizon-pause: ## Pause all Horizon workers
	@$(ARTISAN) horizon:pause
	@printf "$(YELLOW)Horizon paused.$(RESET)\n"

horizon-continue: ## Resume all paused Horizon workers
	@$(ARTISAN) horizon:continue
	@printf "$(GREEN)Horizon resumed.$(RESET)\n"

horizon-status: ## Show Horizon status
	@$(ARTISAN) horizon:status

# =============================================================================
## Maintenance
# =============================================================================

# =============================================================================
## Kubernetes (Helm)
# =============================================================================

HELM_CHART := ./helm/payment-gateway
HELM_RELEASE := payment-gateway

helm-deps: ## Download Helm chart dependencies (bitnami postgresql + redis)
	@helm dependency update $(HELM_CHART)

helm-lint: ## Lint Helm chart for errors
	@helm lint $(HELM_CHART) -f $(HELM_CHART)/values.yaml -f $(HELM_CHART)/values.staging.yaml
	@helm lint $(HELM_CHART) -f $(HELM_CHART)/values.yaml -f $(HELM_CHART)/values.production.yaml

helm-template: ## Render Helm templates locally (dry-run, no cluster needed)
	@helm template $(HELM_RELEASE) $(HELM_CHART) \
		-f $(HELM_CHART)/values.yaml \
		-f $(HELM_CHART)/values.staging.yaml \
		--debug

k8s-staging: ## Deploy to staging namespace (TAG=sha IMAGE=ghcr.io/...)
	@helm upgrade --install $(HELM_RELEASE) $(HELM_CHART) \
		--namespace payment-gateway-staging \
		--create-namespace \
		--values $(HELM_CHART)/values.yaml \
		--values $(HELM_CHART)/values.staging.yaml \
		$(if $(TAG),--set image.tag=$(TAG)) \
		$(if $(IMAGE),--set image.repository=$(IMAGE)) \
		--wait --timeout 5m

k8s-prod: ## Deploy to production namespace (TAG= IMAGE= required)
	@helm upgrade --install $(HELM_RELEASE) $(HELM_CHART) \
		--namespace payment-gateway-prod \
		--create-namespace \
		--values $(HELM_CHART)/values.yaml \
		--values $(HELM_CHART)/values.production.yaml \
		$(if $(TAG),--set image.tag=$(TAG)) \
		$(if $(IMAGE),--set image.repository=$(IMAGE)) \
		--atomic --timeout 10m

k8s-status: ## Show pod/hpa/ingress status (NS=payment-gateway-staging or prod)
	@kubectl get pods,hpa,ingress -n $(or $(NS),payment-gateway-staging)

k8s-rollback: ## Rollback last Helm release (NS=... REV=N)
	@helm rollback $(HELM_RELEASE) $(or $(REV),0) \
		--namespace $(or $(NS),payment-gateway-staging)

# =============================================================================
## Maintenance
# =============================================================================

reconcile: ## Sync all stale Pending payments with providers (HOURS=N, PROVIDER=xxx)
	@$(ARTISAN) payments:reconcile --hours=$(or $(HOURS),2) $(if $(PROVIDER),--provider=$(PROVIDER))

prune: ## Prune old idempotency keys and payment methods (DAYS=N)
	@$(ARTISAN) payments:prune-idempotency-keys --days=$(or $(DAYS),90)
	@$(ARTISAN) payments:prune-payment-methods  --days=$(or $(DAYS),365)

# =============================================================================
## Load testing (k6)
# =============================================================================

k6-create: ## Run create-payment load test (BASE_URL=... API_KEY=...)
	@printf "$(CYAN)Running k6: create-payment...$(RESET)\n"
	@k6 run k6/create-payment.js

k6-list: ## Run list-payments load test (BASE_URL=... API_KEY=...)
	@printf "$(CYAN)Running k6: list-payments...$(RESET)\n"
	@k6 run k6/list-payments.js

k6-webhook: ## Run webhook flood test (BASE_URL=... PROVIDER=yookassa)
	@printf "$(CYAN)Running k6: webhook-flood (PROVIDER=$(or $(PROVIDER),yookassa))...$(RESET)\n"
	@k6 run -e PROVIDER=$(or $(PROVIDER),yookassa) k6/webhook-flood.js

queue-flush: ## Delete all jobs from the failed jobs table
	@$(ARTISAN) queue:flush
	@printf "$(GREEN)Failed jobs flushed.$(RESET)\n"

queue-retry-all: ## Retry all failed jobs
	@$(ARTISAN) queue:retry all
	@printf "$(GREEN)All failed jobs queued for retry.$(RESET)\n"

queue-failed: ## List failed jobs
	@$(ARTISAN) queue:failed

# =============================================================================
## Shell access
# =============================================================================

shell: ## Open a shell inside the app container (as www-data)
	@$(DOCKER_COMPOSE) exec -u www-data $(APP_SERVICE) sh

shell-root: ## Open a root shell inside the app container
	@$(DOCKER_COMPOSE) exec $(APP_SERVICE) sh

tinker: ## Start Laravel Tinker REPL
	@$(DC_EXEC_TTY) php artisan tinker
