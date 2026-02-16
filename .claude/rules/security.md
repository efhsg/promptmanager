# Security Policies

## Scope Enforcement

- Controllers validate user ownership via RBAC rules before operating
- Never trust client-provided IDs without verification against current user
- Use Query scopes like `forUser(int $userId)` to filter data
- Log access attempts with user context using `Yii::warning()` or `Yii::error()`

## Secrets

- No credentials in code — use `.env` or Yii params
- Never log sensitive values (passwords, tokens, API keys)
- Document new environment variables in `.env.example`

## Input Validation

- Validate all user input via model rules or form models
- Sanitize output with `Html::encode()` in views
- Use parameterized queries (ActiveRecord handles this automatically)

## Authentication & Authorization

- Access control via `behaviors()` with RBAC owner rules
- Owner rules defined in `yii/rbac/`:
  - `ProjectOwnerRule`
  - `ContextOwnerRule`
  - `FieldOwnerRule`
  - `PromptTemplateOwnerRule`
  - `PromptInstanceOwnerRule`
  - `NoteOwnerRule`
  - `ClaudeRunOwnerRule`

## File System Access

- Validate file paths against project `root_directory`
- Respect `allowed_file_extensions` whitelist
- Enforce `blacklisted_directories` with exception syntax
