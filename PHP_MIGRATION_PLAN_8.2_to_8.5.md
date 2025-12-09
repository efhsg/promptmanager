# PHP Migration Plan: 8.2 → 8.5

## Executive Summary

| Metric | Value |
|--------|-------|
| Files analyzed | 142 PHP files |
| Issues found | 1 (0 breaking, 1 deprecation) |
| Estimated total effort | < 30 minutes |
| Risk assessment | **LOW** |
| Recommended approach | Big-bang (single upgrade) |

### Key Findings

The codebase is **exceptionally well-prepared** for PHP 8.5. The application already:
- Requires PHP 8.2+ in composer.json
- Uses modern PHP 8.x features (readonly properties, union types, named arguments)
- Has proper type hints throughout
- Uses explicit nullable types (`?Type`) in almost all cases
- Runs on Yii2 2.0.52 (latest version with PHP 8.x support)

Only **one minor deprecation** was found that needs to be addressed.

---

## Environment Requirements

### Target PHP Version
- **PHP 8.5.x** (when released, expected late 2025)

### Current Configuration
- PHP 8.2+ (as specified in composer.json)
- Composer 2.x

### Required Extensions
- ext-ctype
- ext-mbstring
- ext-json
- ext-pdo
- ext-dom
- ext-libxml

---

## Wave 0: Preparation

**Estimated effort:** 1-2 hours

### Tasks

- [ ] Back up database and codebase
- [ ] Create a Git branch for migration testing: `feature/php-8.5-upgrade`
- [ ] Set up PHP 8.5 test environment (Docker recommended)
- [ ] Configure CI pipeline to run tests on PHP 8.5 in addition to 8.2
- [ ] Review Yii2 PHP 8.5 compatibility announcements
- [ ] Document current test pass rate as baseline

### Verification
- Git branch created with latest main/master merged
- PHP 8.5 available in test environment
- CI configuration updated (but not yet enforcing 8.5)

### Rollback
- Delete the feature branch
- No changes to production environment at this stage

---

## Wave 1: Dependencies

**Estimated effort:** 15-30 minutes

### Package Analysis

| Package | Current Version | PHP Requirement | PHP 8.5 Status | Action Required |
|---------|-----------------|-----------------|----------------|-----------------|
| yiisoft/yii2 | 2.0.52 | >=7.3.0 | Compatible | None |
| yiisoft/yii2-bootstrap5 | 2.0.50 | >=7.3 | Compatible | None |
| yiisoft/yii2-symfonymailer | 2.0.4 | >=7.4.0 | Compatible | None |
| yiisoft/yii2-debug | 2.1.27 | >=5.4 | Compatible | None |
| yiisoft/yii2-gii | ~2.2.0 | >=5.4 | Compatible | None |
| ezyang/htmlpurifier | ^4.17 | ~5.6-8.4 | **Monitor** | Check for 8.5 update |
| codeception/codeception | ^5.0.0 | ^8.2 | Compatible | None |
| nadar/quill-delta-parser | ^3.5 | >=7.2 | Compatible | None |
| league/html-to-markdown | ^5.1 | ^7.2.5\|^8.0 | Compatible | None |
| symfony/* components | Various | >=8.2 | Compatible | None |

### Tasks

- [ ] Update `composer.json` PHP requirement (optional):
  ```json
  "php": ">=8.5.0"
  ```
- [ ] Run `composer update` to get latest compatible versions
- [ ] Monitor `ezyang/htmlpurifier` for PHP 8.5 support update
- [ ] Run `composer outdated` and review any security advisories
- [ ] Verify all packages install without conflicts

### Verification
```bash
# Inside Docker container
docker exec pma_yii composer update --dry-run
docker exec pma_yii composer validate
docker exec pma_yii php -v  # Confirm PHP 8.5
```

### Rollback
```bash
git checkout composer.json composer.lock
composer install
```

---

## Wave 2: Breaking Changes

**Estimated effort:** 0 minutes

### Status: NO BREAKING CHANGES FOUND

The codebase scan found **zero breaking changes** that would prevent the upgrade:

| Category | Count | Status |
|----------|-------|--------|
| Removed functions (each, create_function, etc.) | 0 | None found |
| $GLOBALS restrictions | 0 | None found |
| Dynamic properties | 0 | None found |
| String interpolation ${} | 0 | None found |
| utf8_encode/utf8_decode | 0 | None found |
| get_class() without arguments | 0 | None found |

### Verification
```bash
# Run static analysis
docker exec pma_yii vendor/bin/phpstan analyse --level=5 .

# Run tests on PHP 8.5
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii vendor/bin/codecept run functional
```

### Rollback
- No code changes needed; rollback is N/A

---

## Wave 3: Deprecations

**Estimated effort:** 5-10 minutes

### Issues Found: 1

| File | Line | Issue | Fix | Effort | Auto-fixable |
|------|------|-------|-----|--------|--------------|
| `modules/identity/exceptions/UserCreationException.php` | 10 | Implicit nullable parameter | Add `?` prefix | Trivial | Yes (Rector) |

### Issue Details

#### 1. Implicit Nullable Parameter (PHP 8.4 Deprecation)

**Location:** `yii/modules/identity/exceptions/UserCreationException.php:10`

**Current code:**
```php
public function __construct(string $message, int $code = 0, Throwable $previous = null)
```

**Required fix:**
```php
public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
```

**Explanation:** In PHP 8.4+, parameters with a default value of `null` must have an explicitly nullable type declaration using the `?` prefix. This was previously implicit but is now deprecated.

### Tasks

- [ ] Fix implicit nullable parameter in `UserCreationException.php`
- [ ] Run PHPStan to check for any other deprecation notices
- [ ] Verify no deprecation warnings appear in logs during tests

### Automated Fix with Rector

You can automatically fix this and similar issues using Rector:

```bash
# Install Rector if not present
composer require --dev rector/rector

# Create rector.php configuration
cat > rector.php << 'EOF'
<?php
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/yii'])
    ->withRules([
        ExplicitNullableParamTypeRector::class,
    ]);
EOF

# Run Rector
vendor/bin/rector process --dry-run  # Preview changes
vendor/bin/rector process            # Apply changes
```

### Manual Fix

Edit `yii/modules/identity/exceptions/UserCreationException.php`:

```php
<?php

namespace app\modules\identity\exceptions;

use Throwable;
use yii\base\Exception;

class UserCreationException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

### Verification
```bash
# Check for deprecation notices
docker exec pma_yii php -d error_reporting=E_ALL yii help 2>&1 | grep -i deprecat

# Run unit tests
docker exec pma_yii vendor/bin/codecept run unit
```

### Rollback
```bash
git checkout yii/modules/identity/exceptions/UserCreationException.php
```

---

## Wave 4: Modernization (Optional)

**Estimated effort:** 2-4 hours (if pursued)

These changes are **not required** for PHP 8.5 compatibility but can improve code quality:

### Potential Enhancements

#### 1. Add #[\Override] Attribute (PHP 8.3+)
Add the `#[\Override]` attribute to methods that override parent methods. This provides compile-time verification.

```php
#[\Override]
public function rules(): array
{
    return [...];
}
```

#### 2. Typed Class Constants (PHP 8.3+)
Add type declarations to class constants:

```php
// Before
const STATUS_ACTIVE = 10;

// After (PHP 8.3+)
public const int STATUS_ACTIVE = 10;
```

#### 3. Property Hooks (PHP 8.4+)
Consider using property hooks for getter/setter patterns where appropriate.

#### 4. Asymmetric Visibility (PHP 8.4+)
Use `public private(set)` for properties that should be readable but not externally writable.

### Recommendation

Given the codebase is already well-structured with proper typing, these modernizations are **low priority**. Consider implementing them gradually during regular development rather than as part of the migration.

---

## Testing Strategy

### Pre-Migration Testing

- [ ] Run full test suite on PHP 8.2 to establish baseline:
  ```bash
  docker exec pma_yii vendor/bin/codecept run unit
  docker exec pma_yii vendor/bin/codecept run functional
  ```
- [ ] Document test pass rate and any known failures

### Migration Testing

- [ ] Run unit tests on PHP 8.5:
  ```bash
  docker exec pma_yii vendor/bin/codecept run unit
  ```
- [ ] Run functional tests on PHP 8.5:
  ```bash
  docker exec pma_yii vendor/bin/codecept run functional
  ```
- [ ] Run identity module tests:
  ```bash
  docker exec pma_yii vendor/bin/codecept run unit services/
  docker exec pma_yii vendor/bin/codecept run unit models/
  ```

### Manual Smoke Test Checklist

- [ ] User login/logout works correctly
- [ ] Project CRUD operations function
- [ ] Context CRUD operations function
- [ ] Field CRUD operations with options function
- [ ] Prompt template creation and editing works
- [ ] Prompt instance generation produces valid output
- [ ] Quill editor loads and saves correctly
- [ ] File/directory path selector works (if project has root_directory)
- [ ] Copy to clipboard functions in different formats (MD, XML, JSON)

### Performance Testing

- [ ] Compare response times for key operations
- [ ] Monitor memory usage during template generation
- [ ] Check for any new warnings in error logs

---

## Rollback Procedure

### Full Rollback to PHP 8.2

If critical issues are discovered after deploying PHP 8.5:

1. **Immediate: Revert PHP version**
   ```bash
   # Update Docker configuration to use PHP 8.2
   # In docker-compose.yml, change PHP image tag
   docker compose down
   docker compose up -d
   ```

2. **Revert code changes (if any were made)**
   ```bash
   git checkout main
   git branch -D feature/php-8.5-upgrade
   ```

3. **Restore composer.lock**
   ```bash
   git checkout composer.lock
   composer install
   ```

4. **Clear caches**
   ```bash
   docker exec pma_yii yii cache/flush-all
   ```

5. **Verify application**
   ```bash
   docker exec pma_yii vendor/bin/codecept run unit
   ```

---

## Success Criteria

The migration is complete when:

- [ ] All unit tests pass on PHP 8.5
- [ ] All functional tests pass on PHP 8.5
- [ ] No deprecation warnings appear in error logs
- [ ] Application performance is within 5% of PHP 8.2 baseline
- [ ] All manual smoke tests pass
- [ ] Monitoring shows no increase in error rates for 24 hours post-deployment

---

## Yii2 Framework Considerations

### LTS Status
- Yii2 is in **maintenance mode** with active support through 2024+
- PHP 8.x support is actively maintained in Yii2 2.0.x releases
- Current version (2.0.52) is fully compatible with PHP 8.4

### Upgrade Path
If considering future Yii3 migration:
- Yii3 is a complete rewrite with different architecture
- No automated migration path exists
- Current Yii2 codebase will continue to work with PHP 8.5+

### Monitoring Resources
- [Yii2 GitHub Releases](https://github.com/yiisoft/yii2/releases)
- [Yii2 PHP Compatibility](https://www.yiiframework.com/doc/guide/2.0/en/intro-upgrade-from-v1)
- [PHP 8.5 RFC Tracking](https://wiki.php.net/rfc)

---

## Appendix: Files Analyzed

### Application Code (132 files)

**Controllers (6 files)**
- ContextController.php
- FieldController.php
- ProjectController.php
- PromptInstanceController.php
- PromptTemplateController.php
- SiteController.php

**Services (15 files)**
- ContextService.php
- CopyFormatConverter.php
- EntityPermissionService.php
- FieldService.php
- FileFieldProcessor.php
- ModelService.php
- PathService.php
- ProjectService.php
- PromptFieldRenderer.php
- PromptGenerationService.php
- PromptInstanceService.php
- PromptTemplateService.php
- PromptTransformationService.php
- UserDataSeeder.php
- UserPreferenceService.php

**Models (17 files)**
- Context.php, ContextSearch.php
- Field.php, FieldSearch.php, FieldOption.php
- Project.php, ProjectSearch.php, ProjectLinkedProject.php
- PromptInstance.php, PromptInstanceSearch.php, PromptInstanceForm.php
- PromptTemplate.php, PromptTemplateSearch.php
- TemplateField.php
- UserPreference.php
- Query classes (4 files)

**Identity Module (12 files)**
- Module.php
- AuthController.php
- User.php, UserQuery.php
- LoginForm.php, SignupForm.php
- UserService.php, UserDataSeederInterface.php
- UserCreationException.php (contains 1 deprecation)
- ValidationErrorFormatterTrait.php

**Other (82 files)**
- Views, migrations, tests, config files, widgets, etc.

---

## Summary

This migration from PHP 8.2 to 8.5 is **low risk** and should be straightforward:

1. **One code change required:** Fix implicit nullable parameter in `UserCreationException.php`
2. **Dependencies are compatible:** All packages support PHP 8.4+, monitor htmlpurifier for 8.5
3. **Modern codebase:** Already uses PHP 8.x features correctly
4. **Comprehensive tests:** Existing test suite provides good coverage

**Recommended timeline:** This migration can be completed in a single development session (< 1 day total including testing).
