# Packagist Release — Task List

## Pre-flight: Validate composer.json Files

- [ ] Audit all 42 `packages/*/composer.json` — verify `name` follows `lattice/<package>` convention
- [ ] Verify every package has a `description` field
- [ ] Verify every package has `license` set to the correct value
- [ ] Verify `autoload` and `autoload-dev` PSR-4 mappings are correct and match directory structure
- [ ] Verify `require` and `require-dev` dependency versions are valid and consistent across packages
- [ ] Verify `keywords` are present and relevant
- [ ] Verify `homepage` and `support` URLs point to correct repositories
- [ ] Verify `minimum-stability` and `prefer-stable` are set where needed
- [ ] Audit all 4 `starters/*/composer.json` — verify `name` follows `lattice/starter-<type>` convention
- [ ] Verify starters have `type: project` set in composer.json
- [ ] Verify starters have correct `require` versions pointing to `^1.0`
- [ ] Run `composer validate` on all 42 packages
- [ ] Run `composer validate` on all 4 starters
- [ ] Fix any validation errors found

## Packagist Organization Setup

- [ ] Register `lattice` organization on Packagist (or verify it exists)
- [ ] Configure organization profile (description, URL, logo)
- [ ] Add team members with appropriate permissions

## Submit Packages to Packagist

- [ ] Submit `lattice/contracts`
- [ ] Submit `lattice/core`
- [ ] Submit `lattice/compiler`
- [ ] Submit `lattice/module`
- [ ] Submit `lattice/pipeline`
- [ ] Submit `lattice/events`
- [ ] Submit `lattice/http`
- [ ] Submit `lattice/routing`
- [ ] Submit `lattice/database`
- [ ] Submit `lattice/cache`
- [ ] Submit `lattice/queue`
- [ ] Submit `lattice/validation`
- [ ] Submit `lattice/serializer`
- [ ] Submit `lattice/auth`
- [ ] Submit `lattice/authorization`
- [ ] Submit `lattice/jwt`
- [ ] Submit `lattice/oauth`
- [ ] Submit `lattice/social`
- [ ] Submit `lattice/api-key`
- [ ] Submit `lattice/pat`
- [ ] Submit `lattice/http-client`
- [ ] Submit `lattice/mail`
- [ ] Submit `lattice/notifications`
- [ ] Submit `lattice/filesystem`
- [ ] Submit `lattice/scheduler`
- [ ] Submit `lattice/rate-limit`
- [ ] Submit `lattice/observability`
- [ ] Submit `lattice/testing`
- [ ] Submit `lattice/devtools`
- [ ] Submit `lattice/openapi`
- [ ] Submit `lattice/jsonapi`
- [ ] Submit `lattice/problem-details`
- [ ] Submit `lattice/grpc`
- [ ] Submit `lattice/microservices`
- [ ] Submit `lattice/transport-nats`
- [ ] Submit `lattice/transport-rabbitmq`
- [ ] Submit `lattice/transport-sqs`
- [ ] Submit `lattice/transport-kafka`
- [ ] Submit `lattice/workflow`
- [ ] Submit `lattice/workflow-store`
- [ ] Submit `lattice/openswoole`
- [ ] Submit `lattice/roadrunner`

## Submit Starters to Packagist

- [ ] Submit `lattice/starter-api`
- [ ] Submit `lattice/starter-grpc`
- [ ] Submit `lattice/starter-service`
- [ ] Submit `lattice/starter-workflow`

## Verification: composer require

- [ ] Test `composer require lattice/core` in a fresh project
- [ ] Test `composer require` for every remaining package (41 packages)
- [ ] Verify transitive dependencies resolve correctly (e.g., requiring `lattice/auth` pulls in `lattice/core`)
- [ ] Verify version constraints are satisfied across the full dependency graph

## Verification: composer create-project

- [ ] Test `composer create-project lattice/starter-api`
- [ ] Test `composer create-project lattice/starter-grpc`
- [ ] Test `composer create-project lattice/starter-service`
- [ ] Test `composer create-project lattice/starter-workflow`
- [ ] Verify each starter boots and serves a basic request after scaffolding

## Auto-Update Webhooks

- [ ] Configure GitHub webhook for the monorepo to notify Packagist on push
- [ ] Verify webhook fires correctly on a test push/tag
- [ ] Verify Packagist reflects the new version within minutes of tagging
- [ ] Document the webhook setup for future maintainers

## README Badges & Documentation

- [ ] Add Packagist version badge to root README
- [ ] Add Packagist download count badge to root README
- [ ] Add Packagist badges to each package's individual README
- [ ] Verify all badge URLs resolve and display correctly
