# Architecture

## Services & Structure

- Name services after their responsibility (e.g., `CopyFormatConverter`, not `CopyService`).
- If a service exceeds ~300 lines, consider splitting by responsibility.
- Prefer typed objects/DTOs over associative arrays for complex data structures.
- Query logic belongs in Query classes (`models/query/`), not services.

## Key Paths

See `.claude/config/project.md` → File Structure for the complete path reference.

## Controllers & AJAX

Standard patterns:
- AJAX responses use `['success' => bool, 'message' => string, 'data' => mixed]`
- RBAC rules in `yii/rbac/` for owner validation
- Actions follow REST-like naming: `actionCreate`, `actionUpdate`, `actionDelete`

## Query Classes

See `skills/model.md` for query class patterns:
- Method naming: `forUser`, `forProject`, `active`, `byId`, etc.
- Query classes in `models/query/`
