---
name: search
description: Create search models for filtering and listing with ActiveDataProvider
area: data-access
depends_on:
  - rules/architecture.md
  - rules/coding-standards.md
---

# Search Skill

Create search models for filtering/listing following PromptManager patterns.

## Persona

Senior PHP Developer with Yii2 expertise. Focus on data filtering and GridView integration.

## When to Use

- Building list/index pages with filters
- GridView/ListView data providers
- Searchable/filterable collections

## Inputs

- `model`: Base model to search
- `filters`: Searchable attributes
- `sorting`: Default sort order

## File Location

- Search model: `yii/models/<Model>Search.php`

## Search Model Template

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

## Key Patterns

- Extend the model being searched
- Use `andFilterWhere` for optional filters
- Accept `?int $userId` for user scoping
- Return `ActiveDataProvider`
- Safe attributes for filters

## Definition of Done

- Search model created with correct namespace
- Extends base model
- User scoping via parameter
- Filter rules implemented
- Unit test in `yii/tests/unit/models/`
