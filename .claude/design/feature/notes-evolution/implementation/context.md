# Context — Notes Evolution

## Goal
Rename ScratchPad → Note, add NoteType enum, parent_id self-reference, migrate response field to child notes.

## Scope
- R1: Full rename (table, model, controller, RBAC, views, routes, search, sync)
- R2: NoteType enum (note, response, import)
- R3: parent_id self-reference
- R4: Response field → child note migration

## Key References
- Spec: `.claude/design/feature/notes-evolution/spec.md`
- Existing model: `yii/models/ScratchPad.php`
- Existing query: `yii/models/query/ScratchPadQuery.php`
- Existing search: `yii/models/ScratchPadSearch.php`
- Existing controller: `yii/controllers/ScratchPadController.php`
- API controller: `yii/controllers/api/ScratchPadController.php`
- RBAC rule: `yii/rbac/ScratchPadOwnerRule.php`
- RBAC config: `yii/config/rbac.php` (lines 164-191)
- Service pattern: `yii/services/FieldService.php` (standalone, no Component)
- Enum pattern: `yii/common/enums/CopyType.php`
- EntityDefinitions: `yii/services/sync/EntityDefinitions.php` (lines 60-68)
- EntityConfig: `yii/services/projectload/EntityConfig.php`
- QuickSearch: `yii/services/QuickSearchService.php`
- AdvancedSearch: `yii/services/AdvancedSearchService.php`
