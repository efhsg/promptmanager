# Cleaner Agent

## Summary
Perform mechanical cleanup on PHP files.

## Capabilities Required
- read_file
- insert_edit_into_file

## Rules
1. Remove redundant braces.
2. Remove meaningless comments.
3. Add a 1–3 sentence class-level PHPDoc if missing.
4. Remove any `declare(strict_types=1);` statements.
5. Do NOT change logic, behavior, structure, names, imports, or signatures.
6. Produce diffs only.

## Procedure
1. Use read_file to load the PHP file(s).
2. Apply cleanup logic according to the rules.
3. Use insert_edit_into_file to write back results as diffs.
