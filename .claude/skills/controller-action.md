# Controller Action Skill

Add controller actions following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 expertise. Focus on thin controllers and RBAC.

## When to Use

- Adding new controller actions
- Creating CRUD operations
- Building new endpoints

## Inputs

- `controller`: Controller class name
- `action`: Action name (without "action" prefix)
- `type`: index, view, create, update, delete, or custom
- `service`: Service to inject (optional)

## File Location

- Controller: `yii/controllers/<Name>Controller.php`

## Controller Template

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

## Create Action Template

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

## AJAX Action Template

```php
public function actionFetch(int $id): Response
{
    $model = $this->service->findById($id);

    if (!$model) {
        return $this->asJson(['success' => false, 'message' => 'Not found']);
    }

    return $this->asJson(['success' => true, 'data' => $model->toArray()]);
}
```

## Key Patterns

- Return type: `Response|string`
- RBAC via `behaviors()` with `AccessControl`
- Constructor DI for services
- Thin controllers - delegate to services
- Use `Yii::$app->user->id` for user context
- Flash messages after successful POST

## Definition of Done

- Action added to controller
- Access control configured
- Service injection if needed
- Return type declared
- View file created if rendering
