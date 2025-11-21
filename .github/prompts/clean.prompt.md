Remove redundant curly braces that do not affect control flow.
Remove comments that add no meaningful information.
Remove any `declare(strict_types=1);` statements.

Function PHPDoc:
- Remove @param and @return unless they add type detail not in the signature (e.g., array shapes, T[], Collection<User>).
- Keep all @throws annotations.

Replace all fully-qualified class names with `use` imports.
If the class has no class-level PHPDoc, add a 1–3 sentence description of its purpose.

Do not change any code, expressions, imports other than adding needed `use`, names, behavior, or structure.
Output only the cleaned PHP code.
