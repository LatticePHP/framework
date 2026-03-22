# ADR-013: Governance

**Date:** 2026-03-21
**Status:** Accepted

## Context

A framework's governance model affects adoption, contribution, and long-term sustainability. LatticePHP is open source from day one and must establish clear expectations for licensing, contribution, conduct, and security reporting before the first public commit.

## Decision

### License: MIT

LatticePHP and all first-party packages are released under the **MIT License**.

The MIT License was chosen because:
- It is the most widely adopted license in the PHP ecosystem (Laravel, Symfony, and most popular packages use MIT).
- It imposes minimal restrictions on commercial use, modification, and distribution.
- It is compatible with virtually all other open source licenses.
- Corporate legal teams are familiar with and generally approve MIT without friction.

### Required Community Documents

The following documents are maintained in the repository root:

**`CONTRIBUTING.md`:**
- How to set up the development environment.
- Code style requirements (PSR-12 + framework-specific rules enforced by PHP-CS-Fixer).
- How to run tests (PHPUnit + Pest).
- Branch naming conventions (`feature/*`, `fix/*`, `docs/*`).
- Pull request process (template provided, CI must pass, one approval required).
- How to propose new features (RFC process, see ADR-014).
- Commit message format (Conventional Commits).

**`CODE_OF_CONDUCT.md`:**
- Adopts the Contributor Covenant v2.1.
- Designates enforcement contacts.
- Defines unacceptable behavior and consequences.

**`SECURITY.md`:**
- Responsible disclosure process.
- Security contact email.
- Supported versions and security patch policy.
- PGP key for encrypted vulnerability reports.
- Commitment to CVE assignment for confirmed vulnerabilities.
- Timeline: acknowledge within 48 hours, patch within 7-30 days depending on severity.

### Decision-Making Process

- **Day-to-day decisions:** Made by maintainers through PR review.
- **Architectural decisions:** Documented as ADRs (see ADR-014). Require consensus among core maintainers.
- **Substantial API changes:** Require an RFC (see ADR-014). Open for community comment for a minimum of 14 days.
- **Breaking changes:** Only in major versions. Deprecation in minor version, removal in next major.

### Versioning

- Semantic Versioning 2.0.0 (semver.org).
- Pre-1.0 releases may include breaking changes in minor versions (following semver conventions).
- Post-1.0, breaking changes only in major versions.
- All packages share a major version number for consistency (similar to Symfony's approach).

## Consequences

**Positive:**
- MIT license maximizes adoption potential with zero licensing friction.
- Standard community documents set clear expectations for contributors.
- Security policy builds trust with enterprise adopters.
- Semver commitment gives users confidence in upgrade paths.

**Negative:**
- MIT allows proprietary forks with no contribution back. This is an acceptable trade-off for adoption.
- Maintaining community documents requires ongoing effort as the project evolves.
- Shared major versioning constrains individual package evolution.

## Alternatives Considered

1. **Apache 2.0 license:** Provides patent protection but is less familiar in the PHP ecosystem. The marginal benefit does not outweigh the familiarity advantage of MIT.

2. **AGPL or copyleft license:** Would deter corporate adoption and is uncommon in the PHP framework space.

3. **Dual licensing (MIT + commercial):** Adds complexity without clear benefit at this stage. Can be reconsidered later for specific enterprise features.

4. **No formal governance initially:** Would lead to ad-hoc decisions that are hard to reverse. Establishing governance early is low-cost and high-value.
