# Context: Project Load

## Goal
Replace bidirectional sync (`yii/services/sync/`) with a simpler one-directional dump-based project loader.

## Scope
- CLI commands: `project-load/list`, `project-load/load`, `project-load/cleanup`
- Service layer: `ProjectLoadService` (orchestration), supporting classes
- Dynamic column detection via INFORMATION_SCHEMA
- FK remapping, placeholder remapping
- Dry-run mode with detailed reports

## Key References
- Spec: `.claude/design/project-load/spec.md` (v1.8)
- Existing sync: `yii/services/sync/` (7 classes — NOT reused, but patterns referenced)
- SyncController: `yii/commands/SyncController.php` (CLI output pattern)
- Project model: `yii/models/Project.php` (afterDelete, afterSave, TimestampTrait)
- PromptTemplateService: `yii/services/PromptTemplateService.php:116-143` (placeholder regex pattern)
- ClaudeCliService: `yii/services/ClaudeCliService.php:104` (proc_open pattern)
- TimestampTrait: `yii/models/traits/TimestampTrait.php` (uses date('Y-m-d H:i:s'))

## Key Decisions
- Raw SQL inserts (not ActiveRecord) to preserve timestamps and avoid afterSave triggers
- Raw SQL delete (not ActiveRecord) to avoid afterDelete workspace cleanup
- proc_open() for dump import (mysql CLI binary)
- Dynamic column detection from INFORMATION_SCHEMA (not hardcoded lists)
- Temp schema per process: `yii_load_temp_{pid}`
- Matching: --local-project-ids (explicit) or name+user_id (fallback)

## Files to Create
- `yii/commands/ProjectLoadController.php` — CLI controller
- `yii/services/projectload/ProjectLoadService.php` — main orchestration
- `yii/services/projectload/DumpImporter.php` — dump file validation, import, temp schema
- `yii/services/projectload/SchemaInspector.php` — dynamic column detection via INFORMATION_SCHEMA
- `yii/services/projectload/EntityLoader.php` — entity loading, FK remapping, inserts
- `yii/services/projectload/PlaceholderRemapper.php` — placeholder ID remapping in template_body
- `yii/services/projectload/LoadReport.php` — reporting DTO
- `yii/services/projectload/EntityConfig.php` — entity configuration (tables, FKs, order)
- `yii/tests/unit/services/projectload/` — tests
