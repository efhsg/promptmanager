---
allowed-tools: Read, Edit, Write, Bash(docker exec:*)
description: Create a unit test following PromptManager patterns
---

# Create Unit Test

Create a unit test following PromptManager patterns.

## Patterns

- Location: Mirror app structure under `yii/tests/unit/`
- Namespace: `tests\unit\<path>`
- Extend `Codeception\Test\Unit`
- Use `setUp()` for setup (not `_before()`)
- Arrange-Act-Assert pattern
- Use intersection types for mocks: `MockObject&ClassName`
- Load fixtures via `_fixtures()` method

## Example Structure

```php
<?php

namespace tests\unit\services;

use app\models\<Model>;
use app\services\<Name>Service;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\<Model>Fixture;
use tests\fixtures\UserFixture;

class <Name>ServiceTest extends Unit
{
    private <Name>Service $service;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'models' => <Model>Fixture::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new <Name>Service();
    }

    public function testSomeBehavior(): void
    {
        // Arrange
        $userId = 100;

        // Act
        $result = $this->service->fetchList($userId);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testWithMock(): void
    {
        /** @var MockObject&OtherService $mock */
        $mock = $this->createMock(OtherService::class);
        $mock->method('doSomething')->willReturn('result');

        $service = new <Name>Service($mock);
        $result = $service->process();

        $this->assertSame('expected', $result);
    }
}
```

## Test Location by Type

| Type | Test Location |
|------|---------------|
| Service | `yii/tests/unit/services/<Name>ServiceTest.php` |
| Model | `yii/tests/unit/models/<Model>Test.php` |
| Controller | `yii/tests/functional/controllers/<Name>ControllerTest.php` |
| Widget | `yii/tests/unit/widgets/<Name>WidgetTest.php` |
| Enum | `yii/tests/unit/common/enums/<Name>Test.php` |

## Running Tests

```bash
# All unit tests
docker exec pma_yii vendor/bin/codecept run unit

# Single file
docker exec pma_yii vendor/bin/codecept run unit services/<Name>ServiceTest

# Single method
docker exec pma_yii vendor/bin/codecept run unit services/<Name>ServiceTest:testMethod
```

## Task

Create test for: $ARGUMENTS
