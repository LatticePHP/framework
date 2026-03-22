# LatticePHP — Master Task Tracker

> **Read this file FIRST at every session start.**
> Check current progress, pick the next task, update status when done.

## Build Order (Dependency Waves)

```
Wave 1 (no deps):     01-packagist, 02-queue-workers, 03-integration-tests, 04-cli-tools
Wave 2 (foundation):  05-catalyst, 06-ripple
Wave 3 (dashboards):  07-chronos, 08-loom, 09-nightwatch  (depend on 06-ripple optional)
Wave 4 (products):    10-oauth-social, 11-prism, 12-anvil  (depend on 06-ripple)
Wave 5 (next-gen):    13-graphql, 14-ai-module, 15-mcp-module
```

## Progress

| # | Task | Status | Subtasks | Done | Verified |
|---|------|--------|----------|------|----------|
| 01 | [Packagist Release](01-TASK-packagist-release/TASK.md) | `in-progress` | 6 | 1/6 | No |
| 02 | [Queue Workers](02-TASK-queue-workers/TASK.md) | `review` | 7 | 7/7 | Pending |
| 03 | [Integration Tests](03-TASK-integration-tests/TASK.md) | `in-progress` | 8 | 5/8 | No |
| 04 | [CLI Tools](04-TASK-cli-tools/TASK.md) | `review` | 10 | 8/10 | Pending |
| 05 | [Catalyst](05-TASK-catalyst/TASK.md) | `review` | 8 | 6/8 | Pending |
| 06 | [Ripple](06-TASK-ripple/TASK.md) | `review` | 8 | 6/8 | Pending |
| 07 | [Chronos](07-TASK-chronos/TASK.md) | `review` | 9 | 3/9 | Pending |
| 08 | [Loom](08-TASK-loom/TASK.md) | `review` | 8 | 4/8 | Pending |
| 09 | [Nightwatch](09-TASK-nightwatch/TASK.md) | `review` | 9 | 4/9 | Pending |
| 10 | [OAuth & Social](10-TASK-oauth-social/TASK.md) | `pending` | 7 | 0/7 | No |
| 11 | [Prism](11-TASK-prism/TASK.md) | `pending` | 9 | 0/9 | No |
| 12 | [Anvil](12-TASK-anvil/TASK.md) | `pending` | 8 | 0/8 | No |
| 13 | [GraphQL](13-TASK-graphql/TASK.md) | `pending` | 7 | 0/7 | No |
| 14 | [AI Module](14-TASK-ai-module/TASK.md) | `pending` | 8 | 0/8 | No |
| 15 | [MCP Module](15-TASK-mcp-module/TASK.md) | `pending` | 7 | 0/7 | No |

## Status Key

- `pending` — Not started
- `in-progress` — Actively being built
- `blocked` — Waiting on dependency
- `review` — Built, needs verification
- `done` — Verified and complete

## Session Protocol

1. Read this TRACKER.md
2. Read CLAUDE.md for conventions
3. Check memory/ for any saved context
4. Pick next task by wave order (don't skip ahead)
5. Read the task's TASK.md for subtasks
6. Build subtask by subtask, verify each
7. Update TASK.md checkboxes as you go
8. Update this TRACKER.md when task status changes
9. Run full test suite before marking done
10. Save any learnings to memory/
