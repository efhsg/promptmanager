---
allowed-tools: Read, Grep, Glob, Task, AskUserQuestion
description: Analyze codebase and create a refactoring plan to follow code standards
---

# Codebase Standards Analysis & Refactoring Plan

## Reference Documents

Read these files first to understand the project standards:

### Project Rules
@RULES.md

### Current Architecture
@.claude/codebase_analysis.md

---

## Phase Selection

Ask the user which phase(s) to run using AskUserQuestion:

**Question**: "Which analysis phase(s) should I run?"

**Options** (multiSelect: true):
1. **Code Style** — strict_types, inline FQCNs, missing type hints
2. **Service Layer** — file sizes, query logic in services
3. **Data Structures** — array returns, DTO candidates
4. **Architecture** — business logic placement, DI violations, missing Query classes
5. **Test Coverage** — missing service/model tests

If user selects nothing or cancels, run **all phases**.

---

## Phase 1: Code Style Violations

**Severity**: Critical

Search for the following violations:

1. **`declare(strict_types=1)` usage** (should be NONE per RULES.md)
   - Use Grep to search for `declare(strict_types=1)` in `./yii` PHP files

2. **Inline FQCNs in method bodies** (should use `use` imports)
   - Use Grep to search for patterns like `new \\` in PHP files (excluding use statements)

3. **Missing return type hints**
   - Use Grep to find function declarations missing `): ` pattern
   - Exclude `__construct` methods

**Output**: List violations with `file:line` references.

---

## Phase 2: Service Layer Analysis

**Severity**: High

1. **Service file sizes** (candidates for splitting if >300 lines)
   - Use Glob to list `./yii/services/*.php`
   - Read each file and note line counts

2. **Query logic in services** (should be in Query classes)
   - Use Grep to search for `->where`, `->andWhere`, `->orWhere`, `::find()` in `./yii/services`

**Output**: Table of services with line counts and query logic occurrences.

---

## Phase 3: Data Structure Analysis

**Severity**: Medium

1. **Array returns that could be DTOs**
   - Use Grep to search for `): array` in service files

2. **Associative array usage**
   - Use Grep to search for patterns like `['key' =>` in service methods

**Output**: List of methods returning arrays that could benefit from DTOs.

---

## Phase 4: Architecture Violations

**Severity**: High

1. **Business logic in controllers** (should be in services)
   - Use Grep to search for `->save()`, `->delete()`, `->validate()` in `./yii/controllers`

2. **Direct `Yii::$app` access in services** (should use DI)
   - Use Grep to search for `Yii::$app` in `./yii/services`

3. **Missing Query classes for models**
   - Use Glob to list `./yii/models/*.php`
   - Check if corresponding `./yii/models/query/*Query.php` exists

**Output**: List of violations grouped by type.

---

## Phase 5: Test Coverage Gaps

**Severity**: Low

1. **Services without corresponding tests**
   - Compare `./yii/services/*.php` with `./yii/tests/unit/services/*Test.php`

2. **Models without corresponding tests**
   - Compare `./yii/models/*.php` with `./yii/tests/unit/models/*Test.php`

**Output**: List of files missing test coverage.

---

## Output Requirements

After completing the **selected phases**, write findings to `.claude/refactor_plan.md`:

### Per-Phase Output

Each phase should produce:
- **Violations found**: Count and list
- **Specific locations**: `file:line` references
- **Effort estimate**: S/M/L per item

### Summary Section (only if multiple phases run)

1. **Executive Summary**: Overall health score (1-10) based on phases run
2. **Quick Wins**: Changes that can be made immediately with low risk
3. **Suggested Order**: Which files to tackle first based on impact

### Priority Levels

- **Critical**: Direct violations of RULES.md (strict_types, missing type hints, inline FQCNs)
- **High**: Architecture violations (query logic in services, business logic in controllers)
- **Medium**: Data structure improvements (array returns that should be DTOs)
- **Low**: Test coverage gaps, documentation improvements
