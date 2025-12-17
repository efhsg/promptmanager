---
allowed-tools: Read, Edit, Write
description: Create a new service class following PromptManager patterns
---

# Create Service

Create a new service class following PromptManager patterns.

## Patterns

- Location: `yii/services/<Name>Service.php`
- Plain class with public methods
- Constructor dependency injection for other services
- Full type hints on all parameters and returns
- PHPDoc only for `@throws`

## Example Structure

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

## Service without dependencies

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

## Task

Create service: $ARGUMENTS

Include corresponding unit test in `yii/tests/unit/services/`
