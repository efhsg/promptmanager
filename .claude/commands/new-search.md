---
allowed-tools: Read, Edit, Write
description: Create a search model for filtering/listing following PromptManager patterns
---

# Create Search Model

Create a search model for filtering/listing following PromptManager patterns.

## Patterns

- Location: `yii/models/<Model>Search.php`
- Extend the ActiveRecord model
- `search()` method returns `ActiveDataProvider`
- Safe attributes for filtering
- Use `andFilterWhere` for optional filters

## Example Structure

```php
<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * <Model>Search represents the model behind the search form about `app\models\<Model>`.
 */
class <Model>Search extends <Model>
{
    public function rules(): array
    {
        return [
            [['name', 'description'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params, ?int $userId = null): ActiveDataProvider
    {
        $query = <Model>::find();

        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'defaultOrder' => ['name' => SORT_ASC],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['like', 'description', $this->description]);

        return $dataProvider;
    }
}
```

## With Status Filter

```php
public function rules(): array
{
    return [
        [['name', 'status'], 'safe'],
    ];
}

public function search(array $params, ?int $userId = null): ActiveDataProvider
{
    $query = <Model>::find();

    if ($userId) {
        $query->andWhere(['user_id' => $userId]);
    }

    $dataProvider = new ActiveDataProvider([
        'query' => $query,
    ]);

    $this->load($params);

    if (!$this->validate()) {
        return $dataProvider;
    }

    $query->andFilterWhere(['status' => $this->status]);
    $query->andFilterWhere(['like', 'name', $this->name]);

    return $dataProvider;
}
```

## User Scoping

Always accept `?int $userId` parameter and apply it to scope results to the current user.

## Task

Create search model: $ARGUMENTS

Include unit test in `yii/tests/unit/models/`
