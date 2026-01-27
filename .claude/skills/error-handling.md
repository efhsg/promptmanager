# Error Handling Skill

Guidelines for consistent error handling across the codebase.

## Exception Types

| Exception | When to Use |
|-----------|-------------|
| `InvalidArgumentException` | Invalid input that should have been caught earlier |
| `RuntimeException` | Unexpected state during execution |
| `LogicException` | Programming error (bug in code) |
| `DomainException` | Value outside valid domain |
| `UnexpectedValueException` | Value doesn't match expected type/format |

## When to Throw vs Return Null

**Throw when:**
- The operation cannot complete and caller must handle it
- Invalid input that indicates a bug
- Security violations
- Data integrity violations

**Return null when:**
- "Not found" is a normal, expected outcome
- Optional relationships that may not exist
- Default values are acceptable

## Logging Guidelines

```php
// Unexpected failures (bugs, system errors)
Yii::error($message, 'category');

// Recoverable issues (validation, user errors)
Yii::warning($message, 'category');

// Useful debugging info (not for production)
Yii::info($message, 'category');
```

### Log Categories

Use descriptive categories matching the component:
- `prompt.generation` — Prompt generation issues
- `import.youtube` — YouTube import issues
- `field.validation` — Field validation issues

## User-Facing vs Internal Messages

**User-facing (in controllers):**
```php
Yii::$app->session->setFlash('error', 'Unable to save. Please try again.');
```

**Internal (in services/logs):**
```php
Yii::error("Failed to save PromptTemplate id={$id}: {$e->getMessage()}", 'prompt.save');
```

Never expose internal details (IDs, stack traces, SQL) to users.

## Controller Pattern

```php
public function actionUpdate(int $id): Response|string
{
    $model = $this->findModel($id);

    try {
        if ($this->service->update($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
    } catch (ValidationException $e) {
        Yii::$app->session->setFlash('error', $e->getMessage());
    } catch (\Exception $e) {
        Yii::error("Update failed for id={$id}: {$e->getMessage()}", 'controller.update');
        Yii::$app->session->setFlash('error', 'An error occurred. Please try again.');
    }

    return $this->render('update', ['model' => $model]);
}
```

## Service Pattern

```php
public function process(Model $model): Result
{
    if (!$model->validate()) {
        throw new ValidationException('Invalid model data');
    }

    $transaction = Yii::$app->db->beginTransaction();
    try {
        // ... operations
        $transaction->commit();
        return $result;
    } catch (\Exception $e) {
        $transaction->rollBack();
        Yii::error("Process failed: {$e->getMessage()}", 'service.process');
        throw $e; // Re-throw for controller to handle
    }
}
```

## Definition of Done

- [ ] Exceptions are specific, not generic `\Exception`
- [ ] Errors are logged with appropriate level and category
- [ ] User-facing messages are friendly, not technical
- [ ] Transactions are rolled back on failure
- [ ] No silent failures (exceptions logged or re-thrown)
