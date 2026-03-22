# ADR-004: PHP Version Requirement

**Date:** 2026-03-21
**Status:** Accepted

## Context

Choosing a minimum PHP version is one of the most consequential early decisions for a framework. It determines which language features are available, how long the framework can go before a breaking version bump, and how large the potential user base is.

PHP 8.4 (released November 2024) introduced significant language improvements that align directly with LatticePHP's design goals:

- **Property hooks:** Enable computed/validated properties without boilerplate getters/setters, ideal for DTOs, value objects, and configuration.
- **Asymmetric visibility:** `public private(set)` enables immutable-by-default patterns critical for workflow state and event sourcing.
- **Lazy objects:** `ReflectionClass::newLazyProxy()` and `newLazyGhost()` enable framework-level lazy initialization for services, reducing bootstrap cost.
- **`#[\Deprecated]` attribute:** Enables gradual API evolution with compile-time deprecation notices.
- **`new` without parentheses:** Cleaner fluent APIs (`new Pipeline->pipe(...)`).
- **Array find functions:** `array_find()`, `array_find_key()`, `array_any()`, `array_all()` reduce boilerplate.

PHP 8.5 (expected November 2025) is anticipated to bring pipe operator, pattern matching, and additional features that will further benefit the framework.

## Decision

- **Minimum required version:** PHP 8.4.0
- **First-class support:** PHP 8.4 and PHP 8.5
- **CI test matrix:** All packages tested against PHP 8.4 and PHP 8.5 (once released)
- **composer.json constraint:** `"php": "^8.4"`

### Feature Usage Policy

The framework will actively use PHP 8.4 features throughout its codebase:

- Property hooks for configuration objects and DTOs.
- Asymmetric visibility for immutable public state.
- Lazy objects for container-managed service proxies.
- `#[\Deprecated]` for all deprecations.
- Enums for all fixed sets of values.
- Readonly properties and classes where appropriate.
- Attributes for metadata (routing, validation, guards, etc.).
- Fibers for async coordination in RoadRunner/OpenSwoole contexts.
- Named arguments in public APIs where it improves readability.

## Consequences

**Positive:**
- Access to the most expressive version of PHP available, enabling cleaner APIs with less boilerplate.
- Property hooks and asymmetric visibility enable patterns that would otherwise require code generation.
- Lazy objects eliminate the need for custom proxy generation (a major complexity source in Symfony/Doctrine).
- Long support runway: PHP 8.4 has active support until late 2026 and security support until late 2028.
- Positions LatticePHP as a forward-looking framework that does not carry legacy compatibility baggage.

**Negative:**
- Excludes users on PHP 8.3 and below. Shared hosting environments may lag.
- Some CI providers and Docker images may need updates to support 8.4.
- Early adopters on PHP 8.5-dev may encounter edge cases.

## Alternatives Considered

1. **PHP 8.3 minimum:** Would gain a larger user base but lose property hooks and asymmetric visibility, which are architecturally significant for LatticePHP's design patterns. The trade-off is not worth it.

2. **PHP 8.5 minimum:** Too aggressive. PHP 8.5 is not yet released and requiring it would prevent adoption for at least a year after LatticePHP's initial release.

3. **PHP 8.2 minimum with polyfills:** Polyfills cannot replicate language-level features like property hooks or asymmetric visibility. This would force the framework to avoid its most powerful tools.
