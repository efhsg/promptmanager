---
allowed-tools: Read, Edit, Write
description: Create an ActiveRecord model with query class following PromptManager patterns
---

# Create ActiveRecord Model

Create a new ActiveRecord model with query class following PromptManager patterns.

## Model Patterns

- Location: `yii/models/<ModelName>.php`
- No typed public properties for DB columns (magic attributes)
- Type hints on method signatures
- Relations return typed query builders
- Include `tableName()` with `{{%table_name}}` syntax
- Use `TimestampTrait` for `created_at`/`updated_at`

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

## Query Class Patterns

- Location: `yii/models/query/<ModelName>Query.php`
- Chainable scopes returning `static`
- Include `byUser(int $userId)` if user-scoped

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

## Task

Create model: $ARGUMENTS

Include query class and unit tests for both.
