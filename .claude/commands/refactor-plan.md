---
allowed-tools: Read, Grep, Glob, Task
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

## Analysis Instructions

Perform the following analysis using Grep and Glob tools. For each category, search the codebase and document findings.

### Phase 1: Code Style Violations

Search for the following violations:

1. **`declare(strict_types=1)` usage** (should be NONE per RULES.md)
   - Use Grep to search for `declare(strict_types=1)` in `./yii` PHP files

2. **Inline FQCNs in method bodies** (should use `use` imports)
   - Use Grep to search for patterns like `new \\` in PHP files (excluding use statements)

3. **Missing return type hints**
   - Use Grep to find function declarations missing `): ` pattern
   - Exclude `__construct` methods

### Phase 2: Service Layer Analysis

1. **Service file sizes** (candidates for splitting if >300 lines)
   - Use Glob to list `./yii/services/*.php`
   - Read each file and note line counts

2. **Query logic in services** (should be in Query classes)
   - Use Grep to search for `->where`, `->andWhere`, `->orWhere`, `::find()` in `./yii/services`

### Phase 3: Data Structure Analysis

1. **Array returns that could be DTOs**
   - Use Grep to search for `): array` in service files

2. **Associative array usage**
   - Use Grep to search for patterns like `['key' =>` in service methods

### Phase 4: Architecture Violations

1. **Business logic in controllers** (should be in services)
   - Use Grep to search for `->save()`, `->delete()`, `->validate()` in `./yii/controllers`

2. **Direct `Yii::$app` access in services** (should use DI)
   - Use Grep to search for `Yii::$app` in `./yii/services`

3. **Missing Query classes for models**
   - Use Glob to list `./yii/models/*.php`
   - Check if corresponding `./yii/models/query/*Query.php` exists

### Phase 5: Test Coverage Gaps

1. **Services without corresponding tests**
   - Compare `./yii/services/*.php` with `./yii/tests/unit/services/*Test.php`

2. **Models without corresponding tests**
   - Compare `./yii/models/*.php` with `./yii/tests/unit/models/*Test.php`

---

## Output Requirements

After completing the analysis, write the refactoring plan to `.claude/refactor_plan.md` with:

1. **Executive Summary**: Overall health score (1-10) and main issues found
2. **Violations Table**: Markdown table with File, Line, Issue, Severity columns
3. **Refactoring Tasks**: Prioritized list with effort estimates (S/M/L)
4. **Suggested Order**: Which files to tackle first based on impact and dependencies
5. **Quick Wins**: Changes that can be made immediately with low risk

Include specific `file:line` references for each issue found.

### Priority Levels

- **Critical**: Direct violations of RULES.md (strict_types, missing type hints, inline FQCNs)
- **High**: Architecture violations (query logic in services, business logic in controllers)
- **Medium**: Data structure improvements (array returns that should be DTOs)
- **Low**: Test coverage gaps, documentation improvements
