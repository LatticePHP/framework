# ADR-014: ADR and RFC Workflow

**Date:** 2026-03-21
**Status:** Accepted

## Context

As LatticePHP grows, architectural and API decisions will be made by an expanding team of contributors. Without a structured process for proposing, discussing, and recording decisions, the project risks inconsistency, forgotten context, and repeated debates about previously settled questions.

Two types of decisions need different processes:
- **Platform/architectural decisions** that affect the framework's foundation (e.g., supported runtimes, package structure, PHP version).
- **Substantial API designs** that affect how developers use specific packages (e.g., the workflow API, the validation DSL, the routing attribute design).

## Decision

### Architecture Decision Records (ADRs)

**Purpose:** Record significant architectural and platform decisions.

**Stored in:** `docs/adr/`

**Naming:** `ADR-NNN-short-title.md` (e.g., `ADR-015-caching-strategy.md`)

**When to write an ADR:**
- Choosing a technology, library, or standard.
- Defining a framework-wide convention or constraint.
- Making a decision that is costly to reverse.
- Any decision where future contributors will ask "why did we do it this way?"

**Statuses:** `Proposed` -> `Accepted` | `Rejected` | `Superseded by ADR-NNN`

**Template:**

```markdown
# ADR-NNN: Title

**Date:** YYYY-MM-DD
**Status:** Proposed | Accepted | Rejected | Superseded by ADR-NNN

## Context
[Why this decision is needed. What forces are at play.]

## Decision
[What was decided. Be specific and concrete.]

## Consequences
[What follows from this decision -- both positive and negative.]

## Alternatives Considered
[What other options were evaluated and why they were not chosen.]
```

### Requests for Comments (RFCs)

**Purpose:** Propose and refine substantial API designs before implementation.

**Stored in:** `docs/rfc/`

**Naming:** `RFC-NNN-short-title.md` (e.g., `RFC-001-workflow-api.md`)

**When to write an RFC:**
- Designing a new public API for a package.
- Making significant changes to an existing public API.
- Introducing a new pattern or convention that affects multiple packages.
- Any change where community input would improve the design.

**Statuses:** `Draft` -> `Discussion` -> `Accepted` | `Withdrawn`

**Process:**
1. Author creates RFC as a pull request with status `Draft`.
2. When ready for review, status changes to `Discussion`. Minimum 14-day comment period.
3. Core maintainers review comments and iterate on the design.
4. Final decision by core maintainer consensus. Status changes to `Accepted` or `Withdrawn`.
5. Accepted RFCs are merged and referenced in implementation PRs.

**Template:**

```markdown
# RFC-NNN: Title

**Date:** YYYY-MM-DD
**Status:** Draft | Discussion | Accepted | Withdrawn
**Author:** Name (@github-handle)

## Summary
[One-paragraph description of the proposal.]

## Motivation
[Why this change is needed. What problem does it solve?]

## Detailed Design
[The full technical design. Include code examples, API signatures,
configuration options, and database schemas where applicable.]

## Migration Path
[How existing code would migrate to the new API, if applicable.]

## Drawbacks
[Why might we NOT want to do this?]

## Alternatives
[What other designs were considered?]

## Unresolved Questions
[What aspects of the design are still being worked out?]
```

### ADR vs. RFC Decision Guide

| Question | Use ADR | Use RFC |
|----------|---------|---------|
| Is this about choosing a technology or constraint? | Yes | |
| Is this about designing a public API? | | Yes |
| Does it affect the framework's architecture? | Yes | |
| Does it affect how developers write application code? | | Yes |
| Is it a one-time decision? | Yes | |
| Does it need community input on API design? | | Yes |
| Both? Write both. | Yes | Yes |

### Numbering

- ADRs and RFCs have independent numbering sequences.
- Numbers are never reused, even for rejected or withdrawn documents.
- Gaps in numbering are acceptable (a rejected ADR-016 does not mean the next must be ADR-016).

## Consequences

**Positive:**
- All major decisions are documented with context, rationale, and alternatives.
- New contributors can understand why the framework works the way it does.
- RFCs prevent large API designs from being developed in isolation without feedback.
- The 14-day comment period ensures community voice in significant changes.
- Searchable decision history reduces repeated debates.

**Negative:**
- Process overhead for smaller decisions. Maintainers must exercise judgment about what warrants an ADR or RFC.
- Documentation maintenance: superseded ADRs and withdrawn RFCs must be kept (not deleted) for historical context.
- The 14-day RFC period slows down development for significant features.

## Alternatives Considered

1. **GitHub Discussions only:** Informal and hard to search. Decisions get lost in comment threads. Not a permanent record.

2. **ADRs only (no RFCs):** ADRs record decisions but do not provide a structured process for community input on API design. RFCs fill this gap.

3. **RFCs only (no ADRs):** RFCs are heavier weight than needed for platform decisions like "which PHP version" or "which license." ADRs are lighter and more appropriate for these.

4. **CHANGELOG-driven decisions:** CHANGELOGs record what changed, not why. They complement but do not replace ADRs and RFCs.

5. **No formal process:** Works for small teams but breaks down as the contributor base grows. Establishing process early is cheaper than retrofitting it later.
