# Packagist Release — Publish All LatticePHP Packages

## Overview

Register all 42 LatticePHP packages and 4 starter projects on Packagist so that users can install any package via `composer require lattice/<name>` and scaffold new applications via `composer create-project lattice/starter-<type>`. This project covers pre-flight validation of every `composer.json`, organization registration, package submission, webhook configuration for auto-updates, and end-to-end verification of every installable unit.

## Scope

- **42 packages** under `packages/` (core, auth, database, routing, etc.)
- **4 starters** under `starters/` (api, grpc, service, workflow)
- Packagist organization: `lattice`
- Auto-update via GitHub webhooks so Packagist reflects new tags within minutes of a release

## Success Criteria

1. `composer require lattice/core` (and all 41 other packages) resolves and installs correctly.
2. `composer create-project lattice/starter-api` (and the other 3 starters) scaffolds a working project.
3. Every package page on Packagist shows correct description, license, autoload config, and keywords.
4. Pushing a new tag to any package repo triggers an automatic Packagist update via webhook.
5. README badges link to the correct Packagist pages.

## Dependencies

- All 42 packages must have valid `composer.json` files with correct `name`, `description`, `license`, `autoload`, and `require` fields.
- GitHub repository access for webhook configuration.
- Packagist account with organization ownership.
