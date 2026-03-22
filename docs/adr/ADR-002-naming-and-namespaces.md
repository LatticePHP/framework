# ADR-002: Naming and Namespaces

**Date:** 2026-03-21
**Status:** Accepted

## Context

A consistent naming convention is essential for a framework's identity, discoverability, and developer ergonomics. The name must be available on Packagist, not conflict with existing PHP projects, and convey the framework's architectural philosophy. Namespace conventions must be established before any code is written, as they are extremely costly to change later.

The name "Lattice" evokes an interconnected, modular structure -- a regular arrangement of points connected by edges -- which mirrors the framework's architecture of discrete, composable modules connected through well-defined contracts.

## Decision

- **Project name:** LatticePHP
- **Packagist vendor:** `lattice`
- **Root namespace:** `Lattice\`
- **Package naming:** `lattice/{package-name}` (e.g., `lattice/core`, `lattice/http`, `lattice/workflow`)
- **Namespace per package:** `Lattice\{PackageName}\` (e.g., `Lattice\Core\`, `Lattice\Http\`, `Lattice\Workflow\`)
- **Application namespace:** `App\` (convention for user applications)
- **Config prefix:** `lattice.` for framework config keys
- **CLI binary:** `lattice` (e.g., `./vendor/bin/lattice`)
- **Environment prefix:** `LATTICE_` for framework-specific environment variables

### Naming Rules

- Package names use lowercase kebab-case: `lattice/rate-limit`, `lattice/problem-details`.
- Namespace segments use PascalCase: `Lattice\RateLimit\`, `Lattice\ProblemDetails\`.
- Internal/private namespaces use `\Internal\` sub-namespace to signal non-public API.
- Contract/interface packages use the suffix `Contracts`: `Lattice\Contracts\`.

## Consequences

**Positive:**
- Single, consistent vendor prefix across all packages.
- Clear mapping from package name to namespace.
- The `Lattice\` root namespace is short, memorable, and avoids conflicts with known PHP libraries.
- Developers can immediately identify first-party packages by the `lattice/` prefix.

**Negative:**
- The `lattice` vendor name must be secured on Packagist early.
- All documentation, generators, and starters must consistently use these conventions.

## Alternatives Considered

1. **`latticephp/` vendor prefix:** More explicit but verbose. Using just `lattice/` is cleaner and follows the convention set by `symfony/` and `illuminate/`.

2. **`Lattice\Framework\` root namespace:** Adds an unnecessary nesting level. `Lattice\` alone is sufficient and keeps `use` statements shorter.

3. **Different project names (Nexus, Grid, Mesh, Forge):** Either taken on Packagist, too generic, or do not convey the modular interconnection concept as well as "Lattice."
