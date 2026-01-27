# Commit Message Format

## Protected Branches

**NEVER commit directly to `main`.** Always create a feature or fix branch first.

## Prefixes

| Prefix | When |
|--------|------|
| `ADD:` | New features or functionality |
| `CHG:` | Changes to existing behavior |
| `FIX:` | Bug patches |
| `DEL:` | Code or functionality removed |
| `TXT:` | Text in code/templates changed, no functional changes |
| `REFACTOR:` | Code structure changed without changing behavior |
| `DOC:` | Documentation only |

## Rules

- Keep messages concise (~70 characters)
- Description is imperative ("add" not "added")
- Body explains *why*, not *what* (optional)
- No `Co-Authored-By` or AI attribution in commits

## Examples

```
ADD: implement user authentication flow

CHG: update prompt generation to support new field type

FIX: resolve null pointer in template parser

DEL: remove deprecated placeholder syntax

TXT: update error messages for clarity

REFACTOR: extract Delta processing into dedicated service

DOC: update API documentation for fields endpoint
```
