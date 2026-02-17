# Implementation Context

## Goal

Refactor all hardcoded Claude dependencies into a provider-agnostic abstraction layer, so new AI CLI providers can be plugged in by implementing an interface.

## Scope

- 70+ files across models, services, controllers, views, tests, config
- 3 new database migrations
- 5 new interface files + 1 new concrete provider
- 17 test file renames + 3 new test files

## Key References

| What | Where |
|------|-------|
| Full spec | `spec.md` (same directory) — WARNING: 600 lines, do NOT load fully in a session |
| Implementation plan | `plan.md` (same directory) — load relevant phase section only |
| Progress tracking | `impl/todos.md` (this directory) — READ FIRST every session |
| Decisions & deviations | `impl/insights.md` (this directory) |

## Critical Rules

1. **One phase per session** — do not attempt multiple phases
2. **Commit after each phase** — the app must work after every commit
3. **Do not re-read spec.md in full** — use plan.md phase sections instead
4. **Run tests before committing** — `cd /var/www/html/yii && vendor/bin/codecept run unit`
5. **Run migrations on both schemas** — `./yii migrate` + `./yii_test migrate`
6. **Update this todos.md** after completing each phase
