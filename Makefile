# =============================================================================
# LatticePHP — Developer Makefile
# =============================================================================
# Usage:
#   make help          — Show all available commands
#   make install       — Install dependencies
#   make test          — Run full test suite
#   make lint          — Run all quality checks
#   make up            — Start dev server (SQLite)
#   make up-full       — Start full stack (Postgres + Redis + workers)
# =============================================================================

.DEFAULT_GOAL := help
.PHONY: help install update test test-filter test-coverage test-suite lint cs-check cs-fix stan stan-baseline serve up up-full down logs shell docker-test docker-lint build build-prod build-cli clean fresh release release-patch release-minor release-major

# ---------------------------------------------------------------------------
# Help
# ---------------------------------------------------------------------------
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------
# Dependencies
# ---------------------------------------------------------------------------
install: ## Install Composer dependencies
	composer install --prefer-dist --no-interaction

update: ## Update Composer dependencies
	composer update --prefer-dist --no-interaction

# ---------------------------------------------------------------------------
# Testing
# ---------------------------------------------------------------------------
test: ## Run full test suite
	vendor/bin/phpunit --testdox

test-filter: ## Run tests matching a filter (usage: make test-filter F=ClassName)
	vendor/bin/phpunit --filter=$(F) --testdox

test-coverage: ## Run tests with coverage report
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html=coverage --coverage-clover=coverage/clover.xml

test-suite: ## Run a specific test suite (usage: make test-suite S=Workflow)
	vendor/bin/phpunit --testsuite=$(S) --testdox

test-integration: ## Run integration tests only
	vendor/bin/phpunit --configuration=phpunit-integration.xml --testdox

# ---------------------------------------------------------------------------
# Code Quality
# ---------------------------------------------------------------------------
lint: cs-check stan ## Run all quality checks (CS Fixer dry-run + PHPStan)

cs-check: ## Check code style (dry run)
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	vendor/bin/php-cs-fixer fix

stan: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse --memory-limit=2G

stan-baseline: ## Generate PHPStan baseline
	vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline

# ---------------------------------------------------------------------------
# Local Development
# ---------------------------------------------------------------------------
serve: ## Start PHP built-in server
	php bin/lattice serve

# ---------------------------------------------------------------------------
# Docker — Default (SQLite)
# ---------------------------------------------------------------------------
up: ## Start dev server in Docker (SQLite, zero services)
	docker compose up -d app

up-full: ## Start full stack (Postgres + Redis + worker + scheduler)
	docker compose --profile full up -d

down: ## Stop all Docker services
	docker compose --profile full down

logs: ## Tail Docker logs
	docker compose logs -f

shell: ## Open a shell in the app container
	docker compose exec app bash

# ---------------------------------------------------------------------------
# Docker — Tools
# ---------------------------------------------------------------------------
docker-test: ## Run tests in Docker
	docker compose run --rm test

docker-lint: ## Run linting in Docker
	docker compose run --rm lint

# ---------------------------------------------------------------------------
# Docker — Build
# ---------------------------------------------------------------------------
build: ## Build all Docker images
	docker compose build

build-prod: ## Build production image
	docker build --target production -t latticephp/framework:latest .

build-cli: ## Build CLI image
	docker build --target cli -t latticephp/framework:cli .

# ---------------------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------------------
clean: ## Remove caches and generated files
	rm -rf vendor/
	rm -rf .phpunit.cache/ .phpstan-cache/ .php-cs-fixer.cache
	rm -rf coverage/
	rm -f database.sqlite

fresh: clean install ## Clean everything and reinstall

# ---------------------------------------------------------------------------
# Releasing (see CLAUDE.md for full process)
# ---------------------------------------------------------------------------
# Usage:
#   make release-patch   — 1.0.0 → 1.0.1  (bug fixes)
#   make release-minor   — 1.0.1 → 1.1.0  (new features, backwards-compatible)
#   make release-major   — 1.1.0 → 2.0.0  (breaking changes)
#   make release V=1.2.3 — release a specific version

CURRENT_VERSION = $(shell git describe --tags --abbrev=0 2>/dev/null | sed 's/^v//')

release-patch: ## Release a patch version (bug fixes)
	@$(MAKE) release V=$(shell echo $(CURRENT_VERSION) | awk -F. '{printf "%d.%d.%d", $$1, $$2, $$3+1}')

release-minor: ## Release a minor version (new features)
	@$(MAKE) release V=$(shell echo $(CURRENT_VERSION) | awk -F. '{printf "%d.%d.0", $$1, $$2+1}')

release-major: ## Release a major version (breaking changes)
	@$(MAKE) release V=$(shell echo $(CURRENT_VERSION) | awk -F. '{printf "%d.0.0", $$1+1}')

release: ## Release version V (usage: make release V=1.2.3)
ifndef V
	$(error V is required. Usage: make release V=1.2.3)
endif
	@echo ""
	@echo "==> Releasing v$(V)"
	@echo ""
	@echo "Pre-flight checks..."
	@git diff --quiet || (echo "ERROR: Working tree is dirty. Commit or stash changes first." && exit 1)
	@git diff --cached --quiet || (echo "ERROR: Staged changes exist. Commit first." && exit 1)
	@echo "  Working tree clean"
	@echo ""
	@echo "Checking CHANGELOG.md for [$(V)] entry..."
	@grep -q "## \[$(V)\]" CHANGELOG.md || (echo "ERROR: No [$(V)] section found in CHANGELOG.md. Add your changes first." && exit 1)
	@echo "  Changelog entry found"
	@echo ""
	@echo "Running tests..."
	@vendor/bin/phpunit --testdox || (echo "ERROR: Tests failed. Fix before releasing." && exit 1)
	@echo ""
	@echo "Tagging v$(V)..."
	git tag -a "v$(V)" -m "Release v$(V)"
	@echo ""
	@echo "Pushing tag..."
	git push origin "v$(V)"
	@echo ""
	@echo "==> Done! v$(V) tagged and pushed."
	@echo "    GitHub Actions will now:"
	@echo "    1. Create a GitHub Release"
	@echo "    2. Split to all 42 package repos"
	@echo "    3. Tag each package repo with v$(V)"
	@echo ""
	@echo "    Track progress: gh run list --workflow=release.yml"
