# Service Skill

Create service classes following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 expertise. Focus on clean architecture and dependency injection.

## When to Use

- Encapsulating business logic
- Operations spanning multiple models
- Reusable logic needed by controllers

## Inputs

- `name`: Service class name (without "Service" suffix)
- `dependencies`: Other services to inject (optional)
- `operations`: Methods to implement

## File Location

- Service: `yii/services/<Name>Service.php`

## Service Template

```php
<?php

namespace app\services;

use app\models\<Model>;
use yii\db\Exception;

class <Name>Service
{
    public function __construct(
        private readonly OtherService $otherService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function create(int $userId, array $data): <Model>
    {
        $model = new <Model>();
        $model->user_id = $userId;
        $model->load($data, '');

        if (!$model->save()) {
            throw new Exception('Failed to save model');
        }

        return $model;
    }

    public function findByUser(int $userId): array
    {
        return <Model>::find()
            ->where(['user_id' => $userId])
            ->all();
    }

    public function delete(<Model> $model): bool
    {
        return $model->delete() !== false;
    }
}
```

## Service Without Dependencies

```php
<?php

namespace app\services;

use app\models\<Model>;

class <Name>Service
{
    public function fetchList(int $userId): array
    {
        return <Model>::find()
            ->select(['id', 'name'])
            ->where(['user_id' => $userId])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }
}
```

## DI Configuration

Register in `yii/config/web.php` if needed:

```php
'container' => [
    'definitions' => [
        \app\services\<Name>Service::class => \app\services\<Name>Service::class,
    ],
],
```

## Key Patterns

- Constructor DI with readonly properties
- Full type hints on all parameters and returns
- PHPDoc only for `@throws` annotations
- Name services by responsibility (e.g., `CopyFormatConverter`)
- Services < 300 lines; split if larger

## Definition of Done

- Service created with correct namespace
- Constructor DI for dependencies
- All methods have type hints
- @throws documented where applicable
- Unit test created in `yii/tests/unit/services/`
