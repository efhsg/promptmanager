---
name: triage-review
description: Critically assess external code reviews against the actual codebase
area: validation
provides:
  - review_triage
depends_on:
  - rules/coding-standards.md
  - rules/architecture.md
  - rules/security.md
  - rules/testing.md
---

# TriageReview

Take an external code review (pasted text), critically evaluate every comment against the actual codebase and project rules, and produce a structured triage report.

## Persona

Senior PHP 8.2/Yii2 developer. Skeptical reviewer who never takes review comments at face value — verify every claim against the source code and project rules before accepting it. Default stance: the reviewer might be wrong, outdated, or applying conventions that conflict with this project.

## When to Use

- User pastes an external code review and wants it assessed
- User wants to separate actionable feedback from noise
- User needs to prioritize which review comments to address

## Inputs

- `review_text`: The pasted external review (via `$ARGUMENTS`)

## Outputs

- Triage Summary with counts
- Dismissed comments with evidence-based reasons
- Valid comments with file references, fix approach, effort, and severity
- Recommended action order

## Algorithm

### Phase 1: Parse

1. Extract individual comments from the review text
2. For each comment, note:
   - Referenced file(s) and line(s), if any
   - The claim or suggestion being made
   - Implied severity (blocking, suggestion, nit)

### Phase 2: Load Context

1. Read project rules from `.claude/rules/`
2. Run `git status --porcelain` for current state
3. Run `git diff` for uncommitted changes
4. Run `git log --oneline -10` for recent history

### Phase 3: Verify Each Comment

For every extracted comment:

1. **Read the referenced file(s)** — does the code the reviewer mentions actually exist in its described form?
2. **Check technical correctness** — is the reviewer's claim factually accurate?
3. **Check project rules** — does the suggestion align with or conflict with `.claude/rules/`?
4. **Assess severity** — if valid, how impactful is it?

### Phase 4: Classify

Split every comment into **Dismissed** or **Valid**.

#### Dismissal Reasons

A comment is dismissed when it meets any of these criteria:

| Reason | Description |
|--------|-------------|
| **Factually wrong** | The reviewer misread the code or made an incorrect technical claim |
| **Already mitigated** | The issue is handled elsewhere in the codebase (cite the location) |
| **Violates project rules** | The suggestion conflicts with `.claude/rules/` (cite the rule) |
| **Stale reference** | The code the reviewer refers to has changed or no longer exists |
| **Linter-enforced** | The project linter already catches and fixes this automatically |
| **Impractical** | The cost of the change far outweighs its benefit in this context |
| **Framework-handled** | Yii2 or another framework layer already handles this concern |

#### Valid Items

Each valid comment includes:

| Field | Description |
|-------|-------------|
| **File** | Path and line reference |
| **Issue** | What the problem is, confirmed against the source |
| **Fix approach** | Concrete suggestion for resolution |
| **Effort** | S (< 30 min) / M (30 min – 2 hrs) / L (> 2 hrs) |
| **Severity** | Critical / High / Medium / Low |

## Output Format

```
## Triage Summary

**Review comments analyzed:** N
**Dismissed:** N (with reasons)
**Valid:** N (by severity)

## Dismissed Comments

### 1. [Short description of comment]

- **Reviewer said:** [paraphrase]
- **Reason:** [dismissal reason from table above]
- **Evidence:** [file:line or rule reference proving dismissal]

### 2. ...

## Valid Comments

### 1. [Short description of comment]

- **File:** `path/to/file:line`
- **Issue:** [confirmed description]
- **Fix approach:** [concrete steps]
- **Effort:** S | M | L
- **Severity:** Critical | High | Medium | Low

### 2. ...

## Recommended Action Order

Priority-ordered list of valid items to address:

1. [Critical items first, then High, Medium, Low]
2. ...
```

## Definition of Done

- Every comment from the review is analyzed and classified
- Every dismissal cites evidence (file reference or rule)
- Every valid item has file, issue, fix approach, effort, and severity
- Valid items are ordered by priority in the action list
- No code changes are made — this is analysis only
