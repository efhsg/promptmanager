---
allowed-tools: Read, Edit, Write
description: Create a form model following PromptManager patterns
---

# Create Form Model

Create a form model following PromptManager patterns.

## Patterns

- Location: `yii/models/<Name>Form.php`
- Typed public properties for form fields
- `rules()` method for validation
- Processing method (e.g., `save()`, `submit()`, `process()`)

## Example Structure

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

## For AR-backed forms

If the form directly wraps an ActiveRecord for create/update:

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

## Task

Create form: $ARGUMENTS

Include unit test in `yii/tests/unit/models/`
