# Context per Tab - Design Document

## Probleem

De project context wordt opgeslagen in PHP session, die gedeeld is over alle browser tabs. Als je het project wijzigt in tab A, verandert het ook in tab B.

---
copy to copy 3
## Huidige Implementatie

**Bestanden:**
- `yii/components/ProjectContext.php` - context component
- `yii/views/layouts/main.php` - header dropdown
- `yii/controllers/ProjectController.php` - `actionSetCurrent()`

**Opslag:**
1. Session (`currentProjectId`) - gedeeld over alle tabs
2. Database (`user_preference.default_project_id`) - persistent

---

## Kwantitatieve Impact

| Categorie | Bestanden | Geschatte URLs |
|-----------|-----------|----------------|
| Directe links (Html::a, Url::to) | 26 | ~150 |
| GridView row handlers | 6 | 6 |
| ActionColumn links | 6 | ~12 |
| Paginatie links (auto-generated) | 6 | ~60 |
| Breadcrumb links | 5 | ~15 |
| Navigatie menu | 1 | 8 |
| AJAX endpoints (hardcoded) | 3 | 2 |
| **Totaal** | | **~260 URLs** |

**Kritieke hardcoded URLs:**
- `/scratch-pad/import-text` (editor-init.js)
- `/scratch-pad/import-markdown` (editor-init.js, _import-modal.php)

---

## Gekozen Oplossing: URL-based met UrlManager Event

Project ID in de URL meegeven (`?project=5`) met session als fallback.

**Voordelen:**
- Centrale oplossing via Yii's `UrlManager` event - geen 260 losse wijzigingen
- Alle `Url::to()`, `Url::toRoute()`, GridView links automatisch aangepast
- Backwards compatible (session fallback voor oude bookmarks)
- Bookmarkbaar, deelbaar

---

## Implementatie

### Stap 1: ProjectContext uitbreiden

**Bestand:** `yii/components/ProjectContext.php`

```php
public function getCurrentProjectIdFromUrl(): ?int
{
    $projectId = Yii::$app->request->get('project');
    return $projectId !== null ? (int) $projectId : null;
}

public function getEffectiveProjectId(): ?int
{
    // URL heeft prioriteit over session
    return $this->getCurrentProjectIdFromUrl()
        ?? $this->getCurrentProjectIdFromSession();
}
```

### Stap 2: UrlManager event handler

**Bestand:** `yii/components/ProjectUrlBehavior.php` (nieuw)

```php
<?php

namespace app\components;

use Yii;
use yii\base\Behavior;
use yii\web\UrlManager;

class ProjectUrlBehavior extends Behavior
{
    public function events(): array
    {
        return [
            UrlManager::EVENT_AFTER_CREATE_URL => 'addProjectParam',
        ];
    }

    public function addProjectParam($event): void
    {
        // Skip als we niet in web context zijn
        if (Yii::$app->request->isConsoleRequest) {
            return;
        }

        $projectId = Yii::$app->projectContext->getEffectiveProjectId();

        // Alleen toevoegen als er een geldig project is en param nog niet bestaat
        if ($projectId && $projectId > 0 && !str_contains($event->url, 'project=')) {
            $separator = str_contains($event->url, '?') ? '&' : '?';
            $event->url .= $separator . 'project=' . $projectId;
        }
    }
}
```

### Stap 3: Behavior registreren

**Bestand:** `yii/config/main.php`

```php
'urlManager' => [
    'as projectUrl' => \app\components\ProjectUrlBehavior::class,
    // ... existing config
],
```

### Stap 4: Dropdown gedrag aanpassen

**Bestand:** `yii/views/layouts/main.php`

Wijzig de dropdown om bij selectie te redirecten naar de huidige pagina met nieuwe `?project=X`:

```php
Html::dropDownList('project_id', $currentProjectId, $projectListWithAll, [
    'class' => 'form-select me-2',
    'prompt' => 'No Project',
    'onchange' => 'updateProjectInUrl(this.value)',
]);
```

Met JavaScript helper:
```javascript
function updateProjectInUrl(projectId) {
    const url = new URL(window.location.href);
    if (projectId && projectId > 0) {
        url.searchParams.set('project', projectId);
    } else {
        url.searchParams.delete('project');
    }
    window.location.href = url.toString();
}
```

### Stap 5: Hardcoded AJAX URLs fixen

**Bestand:** `npm/src/js/editor-init.js`

Wijzig hardcoded URLs naar dynamische URLs via data-attributes:

```javascript
// Haal URL van data-attribute
const importTextUrl = editorContainer.dataset.importTextUrl;
const importMarkdownUrl = editorContainer.dataset.importMarkdownUrl;
```

**Bestand:** `yii/views/scratch-pad/_import-modal.php`

Gebruik `Url::to()` zodat de behavior de project param toevoegt.

---

## Bestanden te Wijzigen

| Bestand | Wijziging |
|---------|-----------|
| `yii/components/ProjectContext.php` | URL param support toevoegen |
| `yii/components/ProjectUrlBehavior.php` | **Nieuw** - UrlManager behavior |
| `yii/config/main.php` | Behavior registreren |
| `yii/views/layouts/main.php` | Dropdown redirect logica |
| `npm/src/js/editor-init.js` | AJAX URLs dynamisch maken |
| `yii/views/scratch-pad/_import-modal.php` | URL met project param |

**Totaal: 6 bestanden** (vs. 26+ zonder centrale oplossing)

---

## Verificatie

1. Open PromptManager in 2 tabs
2. Tab 1: selecteer Project A → URL toont `?project=1`
3. Tab 2: selecteer Project B → URL toont `?project=2`
4. Navigeer in beide tabs → project blijft behouden per tab
5. Test AJAX import functies in beide tabs
6. Test paginatie en sortering in GridViews
7. Test bookmark zonder `?project` → gebruikt session/default

---

## Risico's en Mitigatie

| Risico | Mitigatie |
|--------|-----------|
| URL wordt langer | Acceptabel, waarde is duidelijk |
| Console commands falen | Check `isConsoleRequest` in behavior |
| Oude bookmarks breken | Session fallback behouden |
| AJAX calls missen param | Data-attributes voor dynamische URLs |
