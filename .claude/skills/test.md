# Test Skill

Create unit tests following PromptManager patterns.

## Persona

Senior PHP Developer with Codeception expertise. Focus on Arrange-Act-Assert and proper mocking.

## When to Use

- Testing new services, models, or components
- User requests test creation
- Behavior changes require test updates

## Inputs

- `class`: Class under test
- `methods`: Methods to test
- `fixtures`: Required fixtures (optional)

## File Locations

| Type | Test Location |
|------|---------------|
| Service | `yii/tests/unit/services/<Name>ServiceTest.php` |
| Model | `yii/tests/unit/models/<Model>Test.php` |
| Controller | `yii/tests/functional/controllers/<Name>ControllerTest.php` |
| Widget | `yii/tests/unit/widgets/<Name>WidgetTest.php` |
| Enum | `yii/tests/unit/common/enums/<Name>Test.php` |

## Test Template

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

## Test Naming & Commands

See `.claude/rules/testing.md` for naming conventions and test commands.

## Key Patterns

- Extend `Codeception\Test\Unit`
- Use `setUp()` not `_before()`
- Arrange-Act-Assert pattern
- Intersection types for mocks: `MockObject&ClassName`
- Load fixtures via `_fixtures()` method
- Mock via constructor injection

## Definition of Done

- Test file mirrors app structure
- All public methods tested (except trivial)
- Edge cases covered
- Uses proper assertions (assertSame, not assertEquals)
- Tests pass: `docker exec pma_yii vendor/bin/codecept run unit`
