# Project Instructions

These instructions apply to all code in this repository.

- This is a PHP 8.2 codebase built on the Yii 2 framework.
- Use full PHP 8.2 type hints for parameters, return types and properties wherever possible.
- Prefer explicit type declarations over implicit or mixed types.
- Follow PSR-12 coding style.
- Only generate the code necessary to solve the requested task; do not add extra helpers, abstractions or structure unless clearly required.
- Prefer dependency injection and small, focused services over service locators or static access.
- Avoid accessing `Yii::$app` from deep inside domain or service classes; inject dependencies instead.
- Always use namespaces and `use` imports rather than fully-qualified class names.
- Do not add `declare(strict_types=1);` to any file.
- When modifying or generating code, do not introduce new comments or docblocks unless specifically requested; preserve and respect existing comments.
