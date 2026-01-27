# Architecture

## Services & Structure

- Name services after their responsibility (e.g., `CopyFormatConverter`, not `CopyService`).
- If a service exceeds ~300 lines, consider splitting by responsibility.
- Prefer typed objects/DTOs over associative arrays for complex data structures.
- Query logic belongs in Query classes (`models/query/`), not services.

## Key Paths

See `.claude/config/project.md` → File Structure for the complete path reference.

## Controllers & AJAX

See `skills/controller-action.md` for:
- Controller templates and patterns
- AJAX response format
- RBAC configuration
- Action templates (create, update, delete, AJAX)

## Query Classes

See `skills/model.md` → Query Method Naming Conventions for:
- Query class templates
- Method naming patterns (`forUser`, `forProject`, `active`, etc.)
- Usage examples
