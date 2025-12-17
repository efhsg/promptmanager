---
allowed-tools: Read, Edit, Write, Grep, Glob
description: Add a controller action following PromptManager patterns
---

# Create Controller Action

Add a controller action following PromptManager patterns.

## Patterns

- Location: `yii/controllers/<Name>Controller.php`
- RBAC via `behaviors()` with `AccessControl`
- Constructor DI for services
- Thin controllers - delegate to services
- Return `Response` or render views

## Access Control Pattern

```php
<?php

namespace app\controllers;

use app\services\<Name>Service;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

/**
 * Controller description (2-3 lines max).
 */
class <Name>Controller extends Controller
{
    private readonly <Name>Service $service;

    public function __construct(
        $id,
        $module,
        <Name>Service $service,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->service = $service;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['create', 'update', 'delete'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }
}
```

## Action Pattern

```php
public function actionCreate(): Response|string
{
    $model = new <Model>();

    if ($model->load(Yii::$app->request->post()) && $model->save()) {
        Yii::$app->session->setFlash('success', 'Item created.');
        return $this->redirect(['index']);
    }

    return $this->render('create', [
        'model' => $model,
    ]);
}
```

## User Ownership

- Use `Yii::$app->user->id` for user-scoped data
- Validate ownership in service layer

## Task

Create action: $ARGUMENTS

Specify controller and action name.
