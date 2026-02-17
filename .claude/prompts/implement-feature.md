**STOP — Complete Phase 0 before any implementation. Do not skip ahead.**

## Role

Senior software engineering agent implementing a scoped change with high code quality and minimal regression risk.

## Invoer

- **FEATURE**: PRJ:{{Feature}}
- **SPEC**: `.claude/design/[FEATURE]/spec.md`
- **PLAN**: `.claude/design/[FEATURE]/plan.md`
- **AGENT_MEMORY**: `.claude/design/[FEATURE]/impl`

## Rules

- [SPEC] is the source of truth for **what** to build
- [PLAN] is the source of truth for **how** to phase the work
- Memory files are orientation only — not authoritative input
- Do not introduce scope beyond spec/plan
- Do not quote large portions of spec into output; refer by heading only

## PHASE 0 — ASSESS AND PLAN (MANDATORY)

**Complete every step before writing implementation code. No exceptions.**

### 0.1 Read the spec

Read [SPEC] in full. Count the number of new + modified files described.

### 0.2 Scope gate

| Files described in spec | Classification | Action |
|-------------------------|----------------|--------|
| 1–15 | **S/M** | Single-session. Create todos with one step per file. |
| 16–40 | **L** | Multi-phase. Create plan.md with phases of max 12 files each. Implement ONE phase per session. |
| 40+ | **XL** | Multi-session. Create plan.md with phases of max 10 files each. Implement ONE phase per session. STOP after each phase. |

**If [PLAN] already exists**: skip plan creation. Read plan, determine current phase from [AGENT_MEMORY]/todos.md.

### 0.3 Create or resume agent memory

**If [AGENT_MEMORY] does NOT exist:**

1. Create directory [AGENT_MEMORY]
2. Create memory files:
    - `context.md` — goal (1 paragraph), scope (in/out), key file references, Definition of Done
    - `todos.md` — see template below
    - `insights.md` — decisions, findings, pitfalls (initially empty except header)
3. If scope is L/XL, also create [PLAN] — see plan template below

**If [AGENT_MEMORY] exists:**

1. Read `todos.md` and `insights.md` in full
2. Read `context.md` for scope and DoD
3. Determine current phase and first unchecked step
4. Continue from there — do NOT re-read full [SPEC]

### 0.4 Gate — confirm before proceeding

Verify all memory files exist and are non-empty.

Output a Phase 0 summary:
- Scope classification (S/M/L/XL)
- Current phase (if resuming)
- Number of steps in current phase
- Goal in 1-2 sentences

**Do not proceed until this gate passes.**

---

## TEMPLATES

### todos.md — S/M scope (single session)

```markdown
# Implementation Todos

## Steps
- [ ] {file or logical unit — one per line}
- [ ] {next step}
- [ ] Run linter + fix issues
- [ ] Run unit tests + fix failures
```

### todos.md — L/XL scope (multi-phase)

```markdown
# Implementation Todos

## Status: Phase {N} in progress

## Phases
- [x] **P1**: {description} — committed: {hash}
- [ ] **P2**: {description}
- [ ] **P3**: {description}

## Current Phase: P{N} — {description}
- [ ] {step 1}
- [ ] {step 2}
- [ ] Run linter + fix issues
- [ ] Run unit tests + fix failures
- [ ] Commit

## Session Log
| Date | Phase | Commit | Notes |
|------|-------|--------|-------|
```

### plan.md (L/XL scope only)

```markdown
# Implementation Plan: [FEATURE]

## Scope
{total files, classification, estimated phases}

## Execution Rules
1. One phase per session
2. Commit after each phase — app must work after every commit
3. Run tests before committing
4. Read impl/todos.md first — every session
5. Only read the current phase section, not the full spec

## Phases

### P1: {description}
**Files:** {list}
**Depends on:** none
**Validation:** {what to check}
**Commit message:** `PREFIX: description`

### P2: {description}
**Files:** {list}
**Depends on:** P1
**Validation:** {what to check}
**Commit message:** `PREFIX: description`

## Dependency Graph
{which phases depend on which}
```

---

## PHASE 1+ — WORK CYCLE (per step)

For each unchecked step in todos.md:

1. **Read** the relevant spec section (NOT the full spec)
2. **Read** reference files mentioned in the spec section
3. **Implement** — follow naming, folders, patterns from spec. When in doubt: follow existing codebase conventions
4. **Verify** — the change compiles/autoloads without errors
5. **Tick** — mark step done in todos.md immediately
6. **Log** — if anything non-obvious happened, note in insights.md

### Step ordering (dependency-safe)

When creating todos, order steps by layer dependency:

1. Interfaces / contracts (no dependencies)
2. Migrations (schema must exist before models)
3. Models + enums + query classes
4. Services + jobs + handlers
5. Controllers + routes + RBAC
6. Views + CSS + JS
7. Config + DI wiring
8. Tests

---

## COMMIT CHECKPOINTS

### S/M scope
Commit once at the end (after linter + tests pass).

### L/XL scope
Commit after **each phase**:

1. Run linter — 0 issues
2. Run unit tests — 0 errors, 0 failures
3. Fix any issues until green
4. Commit with message from plan.md
5. Update todos.md: mark phase done, note commit hash
6. Update insights.md if there were deviations
7. **STOP** — report phase completion to user. Do not start next phase.

---

## BLOCKED / FAILED STEP

If a step cannot be completed:

1. Mark blocked in todos.md: `- [!] {step} — {short reason}`
2. Log details in insights.md
3. Continue with next unblocked step unless blocker makes further work unsafe
4. If fatal: **STOP**, report blocker + options + what you need from the user

---

## CONTEXT MANAGEMENT (anti-crash)

- After completing 8+ steps, check if context is getting large. If so: update all memory files and note "context checkpoint" in insights.md
- Never hold the full spec in working memory during implementation — reference by section heading
- If context compaction occurs: read context.md → todos.md → insights.md, then continue

---

## TERMINATION

### S/M scope — all steps checked

1. Run linter (0 issues)
2. Run unit tests (0 errors, 0 failures)
3. Fix issues until green
4. Note result in insights.md
5. Summary: files changed, test results, open issues

### L/XL scope — current phase complete

1. Run linter + tests (as above)
2. Commit
3. Update todos.md with commit hash
4. Summary: phase completed, files changed, next phase description
5. **STOP** — do not start next phase
