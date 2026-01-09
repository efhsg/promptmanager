---
name: model
description: Create ActiveRecord models with query classes following PromptManager patterns
area: database
depends_on:
  - rules/architecture.md
  - rules/coding-standards.md
---

# Model Skill

Create ActiveRecord models with query classes following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 expertise. Familiar with ActiveRecord patterns and magic attributes.

## When to Use

- Creating a new database entity
- User asks to create a model
- New migration requires corresponding model

## Inputs

- `name`: Model class name (StudlyCase)
- `table`: Database table name (snake_case)
- `attributes`: List of columns and types
- `relations`: Related models (optional)

## File Locations

- Model: `yii/models/<ModelName>.php`
- Query: `yii/models/query/<ModelName>Query.php`

## Model Template

```php
<?php

namespace app\models;

use app\models\query\<ModelName>Query;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Class <ModelName>
 *
 * Brief description of the model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class <ModelName> extends ActiveRecord
{
    use TimestampTrait;

    public static function tableName(): string
    {
        return '{{%<table_name>}}';
    }

    public static function find(): <ModelName>Query
    {
        return new <ModelName>Query(static::class);
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['user_id'], 'integer'],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'name' => 'Name',
            'description' => 'Description',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
```

## Query Class Template

```php
<?php

namespace app\models\query;

use yii\db\ActiveQuery;

class <ModelName>Query extends ActiveQuery
{
    public function byUser(int $userId): static
    {
        return $this->andWhere(['user_id' => $userId]);
    }

    public function active(): static
    {
        return $this->andWhere(['deleted_at' => null]);
    }
}
```

## Key Patterns

- No typed public properties for DB columns (magic attributes)
- Use `TimestampTrait` for created_at/updated_at
- Override `find()` to return custom Query class
- Chainable query scopes return `static`
- PHPDoc for @property annotations

## Definition of Done

- Model file created with correct namespace
- Query class created with user scope
- PHPDoc @property for all columns
- rules() and attributeLabels() implemented
- Unit test created in `yii/tests/unit/models/`
