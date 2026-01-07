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
