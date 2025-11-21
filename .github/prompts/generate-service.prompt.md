# Generate Yii2 Service Prompt

Generate a Yii 2 service class that:

- Uses PHP 8.2 with full type declarations.
- Uses constructor injection for all dependencies.
- Is small, focused, and single-responsibility.
- Contains only the minimum code needed to solve the task.
- Avoids static access and avoids `Yii::$app` inside the service.
- Has clear, intention-revealing method names.
- Preserves existing comments but does not add new comments unless explicitly required.
- Uses expressive private helper methods to organize logic.
- Does not include unnecessary abstractions, interfaces, traits, factories, or layers unless directly relevant.
- Follows Yii 2 directory conventions (`components`, `services`, etc.).

Output only the service class code.
