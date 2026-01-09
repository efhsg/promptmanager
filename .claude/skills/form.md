---
name: form
description: Create form models with validation following PromptManager patterns
area: input-handling
depends_on:
  - rules/coding-standards.md
---

# Form Skill

Create form models following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 expertise. Focus on validation and data handling.

## When to Use

- Creating input forms
- Validating user input before processing
- Wrapping AR models for create/update

## Inputs

- `name`: Form class name
- `fields`: Form fields with types
- `processing`: What happens on submit

## File Location

- Form: `yii/models/<Name>Form.php`

## Basic Form Template

```php
<?php

namespace app\models;

use yii\base\Model;

class <Name>Form extends Model
{
    public ?string $fieldName = null;
    public ?int $numericField = null;

    public function rules(): array
    {
        return [
            [['fieldName'], 'required'],
            [['fieldName'], 'string', 'max' => 255],
            [['numericField'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'fieldName' => 'Field Name',
            'numericField' => 'Numeric Field',
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        // Processing logic
        return true;
    }
}
```

## AR-Backed Form Template

For forms that wrap an ActiveRecord for create/update:

```php
<?php

namespace app\models;

use yii\base\Model;

class Create<Model>Form extends Model
{
    public ?string $name = null;
    public ?string $description = null;

    private ?<Model> $model = null;

    public function __construct(?<Model> $model = null, $config = [])
    {
        $this->model = $model;
        if ($model) {
            $this->name = $model->name;
            $this->description = $model->description;
        }
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string'],
        ];
    }

    public function save(int $userId): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $model = $this->model ?? new <Model>();
        $model->user_id = $userId;
        $model->name = $this->name;
        $model->description = $this->description;

        return $model->save();
    }
}
```

## Key Patterns

- Typed public properties for form fields
- Nullable properties with `?type` and `= null`
- `rules()` for validation
- `save()` or `process()` for handling
- Constructor accepts model for edit forms

## Definition of Done

- Form created with correct namespace
- All fields typed and nullable
- Validation rules implemented
- Processing method implemented
- Unit test in `yii/tests/unit/models/`
