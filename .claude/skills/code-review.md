# Code Review Skill

Perform a structured code review of staged or unstaged changes against project rules and best practices.

## Persona

Senior PHP 8.2 developer with Yii2 expertise. Familiar with PromptManager domain: prompt management system, Quill Delta JSON for rich text, placeholder system (GEN/PRJ/EXT), project linking.

## When to Use

- User asks to review changes, code, or a PR
- Before finalizing changes for commit
- After implementing a feature to catch issues early

## Inputs

- `scope`: Optional file path, directory, or description to narrow review
- `staged`: If true, review only staged changes (default: review all changes)

## Algorithm

1. Run `git status --porcelain` to identify changed files
2. Run `git diff` (or `git diff --staged`) to see specific changes
3. Read each changed file to understand full context
4. Load project rules from `.claude/rules/`
5. **Identify and Run Tests:**
    - Determine relevant unit/functional tests based on changed files.
    - If unsure or changes are broad, run the full suite (`docker exec pma_yii vendor/bin/codecept run unit`).
    - Note any failures.
6. Evaluate each file against the review checklist
7. Categorize findings by severity
8. Compile report with actionable recommendations

## Review Checklist

### 1. Correctness

- Logic is correct and handles edge cases
- No obvious bugs or regressions
- Error handling is appropriate (no silent failures)

### 2. Style & Standards

Per `.claude/rules/coding-standards.md`, plus:

- No `declare(strict_types=1)`
- Full type hints on params/returns/properties
- `use` imports, no inline FQCNs
- No typed public properties for AR DB columns
- Docblocks only for `@throws` or Yii2 magic
- Prefer early returns over deep nesting
- No commented-out code
- No unnecessary curly braces

### 3. Architecture

Per `.claude/rules/architecture.md`, plus:

- Complex queries belong in Query classes (`models/query/`)
- Config values via `Yii::$app->params`, not hardcoded
- Controllers delegate to services
- Services < 300 lines

### 4. PromptManager Domain

- Correct user ownership validation via RBAC rules
- Quill Delta JSON handled correctly
- Placeholder parsing (GEN:, PRJ:, EXT:) follows patterns
- Project linking respects access controls

### 5. Security

Per `.claude/rules/security.md`:

- Owner verification on all operations
- Input validation via model rules
- No PII in logs
- Parameterized queries (via ActiveRecord)

### 6. Tests

Per `.claude/rules/testing.md`:

- **Execution:** Relevant tests (or full suite) passed.
- New behavior has tests
- Test naming follows pattern
- Mocks via constructor injection

## Severity Levels

| Level | Criteria | Action |
|-------|----------|--------|
| **Critical** | Security vulnerabilities, data corruption risks, breaking changes without migration, **Test Failures** | Must fix before merge |
| **High** | Bugs, incorrect logic, missing error handling, violated architecture rules | Should fix before merge |
| **Medium** | Missing tests, code style violations, suboptimal patterns | Recommended to fix |
| **Low** | Minor improvements, documentation gaps, naming suggestions | Consider fixing |

## Output Format

```
## Review Summary

**Files reviewed:** N files
**Tests ran:** [List of suites/files ran]
**Status:** PASS | PASS WITH COMMENTS | NEEDS CHANGES

## Findings

### Critical
- (none or list with file:line references)

### High
- `path/to/file:123` — description of issue

### Medium
- `path/to/file:45` — description of issue

### Low
- `path/to/file:67` — suggestion

## Recommendations

1. Specific action to take
2. ...
```

## Definition of Done

- All changed files reviewed against checklist
- Findings reference specific file and line numbers
- Each finding has clear, actionable description
- **Tests executed and results incorporated into review**
- Overall status reflects severity of findings
- Output follows the required format