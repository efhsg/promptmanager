# Testing

## Stack

- **Codeception** for all tests
- Run all: `docker exec pma_yii vendor/bin/codecept run unit`
- Run single: `docker exec pma_yii vendor/bin/codecept run unit tests/unit/path/ToTest.php`

## Requirements

- Add/adjust the smallest relevant Codeception test when it meaningfully reduces regression risk.
- When introducing migrations, ensure they run on both app and test schemas (`yii` and `yii_test`).
- Mock services via constructor injection, not `Yii::$app` container manipulation.
- Use fixtures for database state; don't rely on production data assumptions.

## Test Naming

Pattern: `test{Action}{Condition}` or `test{Action}When{Scenario}`

```php
public function testCalculatesTotalWhenAllItemsPresent(): void

public function testThrowsExceptionWhenUserNotFound(): void

public function testReturnsNullWhenInputIsEmpty(): void
```

## No Tests For

- Simple getters/setters
- Framework code
- Third-party libraries
