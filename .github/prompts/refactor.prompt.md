# Yii2 Refactor Prompt

Refactor the selected Yii 2 PHP code according to these rules:

- Preserve behaviour exactly.
- Apply PSR-12 formatting and modern PHP 8.2 patterns.
- Use explicit types everywhere possible.
- Remove dead code, unused variables, redundant conditions, or unneeded temporary variables.
- Improve naming to be intention-revealing.
- Split large methods into smaller private helpers.
- Remove unnecessary coupling to `Yii::$app`; prefer injected dependencies.
- Keep controllers thin; move logic into services where appropriate.
- Preserve existing comments; do not add new comments unless necessary to clarify behaviour.
- Apply SOLID, DRY, YAGNI principles without adding unnecessary abstractions.
- Do not change public APIs unless the prompt explicitly allows it.

Output only the refactored code.
