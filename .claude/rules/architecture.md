# Architecture

## Services & Structure

- Name services after their responsibility (e.g., `CopyFormatConverter`, not `CopyService`).
- If a service exceeds ~300 lines, consider splitting by responsibility.
- Prefer typed objects/DTOs over associative arrays for complex data structures.
- Query logic belongs in Query classes (`models/query/`), not services.
- External API integrations belong in dedicated client classes (`clients/`).

## Key Paths

| Path | Contents |
|------|----------|
| `yii/controllers/` | Application controllers |
| `yii/services/` | Business logic services |
| `yii/models/` | ActiveRecord models |
| `yii/models/query/` | Query classes |
| `yii/views/` | View templates |
| `yii/migrations/` | Database migrations |
| `yii/tests/` | Codeception tests |
| `yii/modules/identity/` | Auth module |
| `yii/common/enums/` | Enums (CopyType) |
| `yii/common/constants/` | Constants (FieldConstants) |
| `yii/rbac/` | RBAC rules |
| `yii/widgets/` | Custom widgets |
| `yii/presenters/` | Presenter classes |
| `yii/components/` | Application components |
| `yii/helpers/` | Helper classes |
| `npm/` | Frontend build scripts |

## Controller Patterns

- Return type: `Response|string` for standard actions.
- Use `$this->redirect()` after successful POST; render form on validation failure.
- Access control via `behaviors()` with RBAC owner rules.
- Flash messages: `Yii::$app->session->setFlash('success', '...')` after successful operations.

## AJAX Responses

- Success: `$this->asJson(['success' => true, 'data' => ...])`.
- Error: `$this->asJson(['success' => false, 'message' => '...'])`.
- Use `Yii::$app->request->isAjax` to detect AJAX requests.

## Query Classes

Use query class methods instead of inline `->andWhere()`. Add new methods to Query classes when needed.

### Location

Query classes live in `models/query/` and extend `ActiveQuery`.

### Naming Conventions

| Pattern | Use When | Example |
|---------|----------|---------|
| `active()` | Filter by status | `->active()` |
| `withX($x)` | Filter by relation/value | `->withProject($projectId)` |
| `hasX()` | Filter for non-null | `->hasContent()` |
| `inX($values)` | Filter by set membership | `->inStatus(['draft', 'active'])` |
| `byX($x)` | Filter by ownership/key | `->byUser($userId)` |
| `alphabetical()` | Sort by name A-Z | `->alphabetical()` |
| `orderedByX()` | Custom sort order | `->orderedByCreatedAt()` |
| `latest()` | Most recent first | `->latest()` |

### Structure

```php
class ProjectQuery extends ActiveQuery
{
    public function byUser(int $userId): static
    {
        return $this->andWhere(['user_id' => $userId]);
    }

    public function active(): static
    {
        return $this->andWhere(['status' => Project::STATUS_ACTIVE]);
    }

    public function withLabel(string $label): static
    {
        return $this->andWhere(['label' => $label]);
    }

    public function alphabetical(): static
    {
        return $this->orderBy(['name' => SORT_ASC]);
    }
}
```

### Usage

```php
// Good: chainable, readable
$projects = Project::find()
    ->byUser($userId)
    ->active()
    ->alphabetical()
    ->all();

// Bad: inline conditions (avoid)
$projects = Project::find()
    ->andWhere(['user_id' => $userId])
    ->andWhere(['status' => 'active'])
    ->orderBy(['name' => SORT_ASC])
    ->all();
```
