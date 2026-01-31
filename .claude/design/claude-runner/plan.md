# Plan: Docker Config Fix + Runtime Diagnostics

## Problem

`PATH_MAPPINGS` was empty and project directories weren't mounted in the container.
`translatePath()` returned host paths unchanged, `is_dir()` failed, everything fell
through to managed workspaces. The UI showed misleading status messages.

## Solution

### Infrastructure
- **docker-compose.yml**: Added `${PROJECTS_ROOT}:/projects:rw` volume mount
- **.env.example**: Documented `PROJECTS_ROOT` + `PATH_MAPPINGS` with correct examples

### Backend (ClaudeCliService)
- `checkClaudeConfigForPath()`: Returns `pathStatus` (`not_mapped` | `not_accessible` | `no_config` | `has_config`), `pathMapped`, `requestedPath`, `effectivePath`
- `determineWorkingDirectory()`: Logs `Yii::warning()` at each fallthrough point
- `execute()`: Returns `requestedPath`, `effectivePath`, `pathMapped` in response

### Controllers
- `ProjectController::actionCheckClaudeConfig()`: Passes `pathStatus` + `pathMapped`
- `ScratchPadController::actionRunClaude()`: Passes diagnostic fields

### Views
- `claude.php` (`checkConfigStatus()`): Uses `pathStatus` with `alert-danger` for infra issues
- `_form.php`: Shows path status warnings in project form

### Console
- `ClaudeController::actionDiagnose`: Checks CLI binary, PATH_MAPPINGS, per-project status

### Tests
- `testCheckConfigReturnsNotMappedWhenNoMappings`
- `testCheckConfigReturnsNotAccessibleWhenMappedButMissing`
- `testCheckConfigReturnsHasConfigWhenConfigExists`
- `testCheckConfigReturnsNoConfigWhenDirExistsButEmpty`

## Verification

```bash
docker exec pma_yii ./yii claude/diagnose
docker exec pma_yii vendor/bin/codecept run unit tests/unit/services/ClaudeCliServiceTest.php
```
