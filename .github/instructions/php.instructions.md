# PHP Instructions

Apply these rules to PHP code in this repository, in addition to the global project instructions.

## PHPDoc and Comments

- When adding new PHPDoc blocks, only document exceptions thrown (e.g. `@throws`) unless explicitly instructed otherwise.
- Do not generate `@param` or `@return` annotations when types are already expressed in PHP signatures.
- Do not introduce new inline comments or block comments unless explicitly requested; preserve existing comments.

## Yii 2 Conventions

- Follow the Yii 2 coding and directory structure used in this project (e.g. controllers under `controllers`, models under `models`, components under `components`, etc.).
- Rely on Yii 2 alias resolution (`@app`, `@runtime`, etc.) rather than hardcoding paths.
- For Yii 2 controllers:
- Use the `actionName` pattern (e.g. `actionIndex`, `actionCreate`).
  - Keep controllers thin and delegate business logic to services.
- For Yii 2 ActiveRecord:
  - Move complex query logic into dedicated Query classes (e.g. `StudentQuery`).
  - Prefer expressive query methods (`active()`, `forCurrentSchoolYear()`) over repeating conditions.

## Services and Components

- Implement service classes as focused, single-responsibility objects.
- Use constructor injection for dependencies.
- Give all public/protected methods explicit return types.
- Keep methods short and intention-revealing; extract private helpers instead of writing large monolithic methods.

## PHP Micro Style

- Avoid unnecessary extra scopes or braces that do not improve readability (e.g. `if (true) { ... }` or redundant nested blocks).
