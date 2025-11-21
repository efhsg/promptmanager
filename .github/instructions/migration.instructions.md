# Migration Instructions

Apply these rules when generating or modifying Yii 2 migration classes, in addition to the global project and PHP instructions.

## General Structure

- Use Yii 2 class-based migrations with `safeUp()` and `safeDown()` methods.
- Use the `{{%table_name}}` syntax to respect table prefixes.
- Keep migrations limited to schema changes and minimal, necessary data updates.

## Schema Changes

- Use Yii migration helpers (`createTable`, `addColumn`, `dropColumn`, `addForeignKey`, etc.) instead of raw SQL when appropriate.
- Choose column types consistent with the existing schema (e.g. `integer()`, `string(255)`, `boolean()`, `dateTime()`).
- Name indices and foreign keys consistently with existing migration naming patterns.

## Data Changes

- Only include data manipulation when strictly necessary for the schema transition.
- Avoid embedding business logic or domain rules in migrations.

## Reversibility and Safety

- Implement `safeDown()` to reverse `safeUp()` unless genuinely impossible.
- If irreversible, use the project's established pattern (e.g. throw `new \yii\db\IrreversibleMigration()`).

## Comments and Output

- Do not introduce comments unless absolutely necessary.
- Do not print output (`echo`, `var_dump`) inside migrations.
