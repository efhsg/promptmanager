# Refactor

Perform structural code improvements without changing observable behavior.

## Persona

Senior PHP 8.2 engineer focused on code quality. Prioritizes maintainability and readability while strictly preserving existing behavior.

## When to Use

- User asks to refactor specific files or code
- After feature implementation to clean up code
- To reduce duplication or coupling identified in review

## Inputs

- `scope`: File path, directory, or class name to refactor (required)

## Constraints

These constraints are non-negotiable:

1. **No behavior change** — All existing tests must stay green
2. **SOLID, DRY, YAGNI** — Apply only to eliminate duplication or tight coupling; stop when goal is met
3. **Comments policy**:
   - Keep all existing comments
   - Add new comments only for: static-analysis silencing, PHPDoc `@throws`
   - Do NOT add docblocks unless specifically requested
4. **No strict types** — Never add `declare(strict_types=1);`
5. **Only refactor** — Do not add features, fix bugs, or make other changes
6. **Follow PSR-12** — Always use braces for control structures

## Algorithm

1. Read the target file(s) to understand current structure
2. Identify refactoring opportunities:
   - Duplicated code → Extract method/trait
   - Long methods → Split into focused methods
   - Deep nesting → Early returns
   - Tight coupling → Dependency injection or interfaces
3. Run existing tests to establish baseline (vanuit `/var/www/worktree/main/yii`):
   ```bash
   vendor/bin/codecept run unit {relevant-test-path}
   ```
4. Apply refactoring changes
5. Run linter to fix formatting:
   ```bash
   vendor/bin/php-cs-fixer fix {file} --config=../.php-cs-fixer.dist.php
   ```
6. Re-run tests to verify no behavior change:
   ```bash
   vendor/bin/codecept run unit {relevant-test-path}
   ```
7. If tests fail, revert changes and report issue

## Refactoring Patterns

### Extract Method
```php
// Before
public function process(): void
{
    // validation logic (10 lines)
    // processing logic (10 lines)
}

// After
public function process(): void
{
    $this->validate();
    $this->execute();
}
```

### Early Return
```php
// Before
public function calculate(int $value): int
{
    if ($value > 0) {
        return $value * 2;
    } else {
        return 0;
    }
}

// After
public function calculate(int $value): int
{
    if ($value <= 0) {
        return 0;
    }

    return $value * 2;
}
```

### Replace Conditional with Polymorphism
```php
// Before: switch on type
// After: interface + implementations
```

## Output Format

```
## Refactoring Summary

**Files refactored:** N files
- `path/to/file1.php`
- `path/to/file2.php`

**Changes applied:**
- Extracted method `validateInput()` from `process()`
- Converted 3 if-else blocks to early returns

**Tests:** All green (N tests, M assertions)

**Linter:** Passed
```

## Definition of Done

- [ ] All targeted files have been refactored
- [ ] No observable behavior has changed
- [ ] All existing tests pass
- [ ] Linter passes without errors
- [ ] No new `declare(strict_types=1)` added
