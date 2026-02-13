# Feature: Note Index Project Dropdown

## Overzicht

Toevoegen van een project dropdown aan de note/index zoekfunctionaliteit, zodat gebruikers notes kunnen filteren op één specifiek project of over alle projecten.

## Probleemstelling

De huidige implementatie van note-index-search filtert notes op basis van de **globale projectcontext** (session/URL parameter). Gebruikers kunnen niet binnen de zoekinterface zelf projecten selecteren. Dit beperkt de zoekflexibiliteit:

1. Om in een ander project te zoeken, moet de gebruiker eerst de globale projectcontext wijzigen
2. De zoekcontext is impliciet (afhankelijk van header dropdown) in plaats van expliciet in het zoekformulier

## Gebruikersbehoeften

1. **Directe projectselectie** — Gebruiker wil binnen het zoekformulier het zoekbereik bepalen
2. **Default naar context-project** — Het actieve project (uit header) is standaard geselecteerd
3. **Alle projecten optie** — Optie om in alle eigen projecten te zoeken

## Scope

### In scope (Fase 1: Single-select)
- Single-select dropdown voor projectselectie in het zoekformulier
- Default-waarde: huidige project-context, of "All Projects" als context = "All Projects"
- "All Projects" optie bovenaan de lijst (waarde: `-1`)
- Combinatie met bestaande zoek- en type-filters
- Reset-knop wist ook projectselectie naar context-default

### Buiten scope
- Multi-select (mogelijk in fase 2)
- Wijzigen van de globale projectcontext vanuit deze dropdown
- Zoeken in gelinkte (EXT) projecten
- Project-suggesties of -autocomplete
- Opslaan van zoekvoorkeuren

## Acceptatiecriteria

### AC1: Single-select dropdown
- [ ] Dropdown toont alle projecten van de gebruiker
- [ ] "All Projects" optie bovenaan (waarde: `-1`, consistent met `ProjectContext::ALL_PROJECTS_ID`)
- [ ] Default: huidige project-context, of "All Projects" als context = "All Projects"
- [ ] Na selectie: zoeken filtert op geselecteerd project

### AC2: Context-integratie
- [ ] Dropdown default volgt de actieve projectcontext
- [ ] Zoekfilters (project, tekst, type) combineren correct (AND)
- [ ] Paginering werkt met projectfilter
- [ ] Reset-knop wist projectselectie naar context-default

### AC3: UX
- [ ] Dropdown past in bestaand zoekformulier (card-header)
- [ ] Gebruikt native `<select>` met Bootstrap styling (consistent met bestaande `type` dropdown)
- [ ] Label: geen (consistent met `q` en `type` velden)
- [ ] "All Projects" is eerste dropdown-optie (geen placeholder — native select ondersteunt dit niet visueel)

## Edge Cases

| Case | Verwacht gedrag |
|------|-----------------|
| Context = "All Projects" | Dropdown default = "All Projects" (`-1`) |
| Context = specifiek project | Dropdown default = dat project |
| Geselecteerd project verwijderd | `forUser($userId)` scope filtert automatisch, geen resultaten voor dat project |
| Gebruiker heeft geen projecten | Dropdown toont alleen "All Projects"; geen speciale behandeling nodig |
| URL-manipulatie met andermans project-id | `forUser($userId)` scope voorkomt data-lekkage |

## Dependencies

- Bestaand: `NoteSearch` model (`yii/models/NoteSearch.php`)
- Bestaand: `NoteQuery::forUser()`, `NoteQuery::forUserWithProject()` scopes
- Bestaand: `projectList` variabele in controller (via `ProjectService::fetchProjectsList()`)
- Bestaand: Projectcontext logica (`ProjectContext::ALL_PROJECTS_ID = -1`)

## Implementatieplan

> **Stapvolgorde:** 1 → 2 → 3 → 4 → 5 (fixtures eerst uitbreiden voordat tests geschreven worden)

### Stap 1: NoteSearch model aanpassen

**Bestand:** `yii/models/NoteSearch.php`

Voeg publiek attribuut toe. De signature blijft backward-compatible:

```php
use app\components\ProjectContext;
use yii\base\Model;

class NoteSearch extends Note
{
    public ?string $q = null;
    public ?int $project_id = null;  // ← Nieuw: filter attribuut

    public function rules(): array
    {
        return [
            [['name', 'type', 'q'], 'safe'],
            [['project_id'], 'integer'],  // ← Nieuw
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * Signature blijft backward-compatible met bestaande aanroepen.
     *
     * @param array $params Request parameters
     * @param int $userId Current user ID
     * @param int|null $currentProjectId Context project (gebruikt als default voor project_id)
     * @param bool $isAllProjects True als context = "All Projects"
     * @param bool $showChildren Include child notes
     */
    public function search(
        array $params,
        int $userId,
        ?int $currentProjectId = null,
        bool $isAllProjects = false,
        bool $showChildren = false
    ): ActiveDataProvider {
        // Load form data EERST om $this->project_id te vullen
        $this->load($params);

        // Bepaal effectief project_id:
        // 1. Form input (als aanwezig)
        // 2. Anders: context default
        $effectiveProjectId = $this->project_id;
        if ($effectiveProjectId === null) {
            $effectiveProjectId = $isAllProjects
                ? ProjectContext::ALL_PROJECTS_ID
                : $currentProjectId;
            // Zet project_id zodat form correct default toont
            $this->project_id = $effectiveProjectId;
        }

        // Build query based on project selection
        if ($effectiveProjectId === ProjectContext::ALL_PROJECTS_ID || $effectiveProjectId === null) {
            $query = Note::find()->forUser($userId);
        } else {
            $query = Note::find()->forUserWithProject($userId, $effectiveProjectId);
        }

        if (!$showChildren) {
            $query->topLevel();
        }

        $query->withChildCount()->orderedByUpdated();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['type' => $this->type]);

        if ($this->q !== null && $this->q !== '') {
            $query->searchByTerm($this->q);
        }

        return $dataProvider;
    }
}
```

### Stap 2: NoteController::actionIndex aanpassen

**Bestand:** `yii/controllers/NoteController.php`

Minimale wijziging — alleen `$projectOptions` toevoegen. Signature-aanroep blijft identiek:

```php
public function actionIndex(): string
{
    $searchModel = new NoteSearch();
    $currentProject = $this->projectContext->getCurrentProject();
    $isAllProjects = $this->projectContext->isAllProjectsContext();
    $showChildren = (bool) Yii::$app->request->get('show_all', 0);

    // Signature blijft ongewijzigd
    $dataProvider = $searchModel->search(
        Yii::$app->request->queryParams,
        Yii::$app->user->id,
        $currentProject?->id,
        $isAllProjects,
        $showChildren
    );

    // Build project dropdown options: "All Projects" eerst, dan gebruikersprojecten
    $projectOptions = [ProjectContext::ALL_PROJECTS_ID => 'All Projects']
        + Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id);

    return $this->render('index', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider,
        'currentProject' => $currentProject,
        'isAllProjects' => $isAllProjects,  // Blijft context-based voor alert banner
        'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
        'projectOptions' => $projectOptions,  // ← Nieuw
        'showChildren' => $showChildren,
    ]);
}
```

### Stap 3: View aanpassen

**Bestand:** `yii/views/note/index.php`

Voeg project dropdown toe na type dropdown. Let op:
- Geen `prompt` — "All Projects" is al de eerste optie in `$projectOptions`
- `project_id` wordt door model naar context-default gezet, dus dropdown toont correcte default

```php
<?= $form->field($searchModel, 'project_id', [
    'options' => ['class' => 'mb-0'],
])->dropDownList(
    $projectOptions,
    [
        'class' => 'form-select',
    ]
)->label(false) ?>
```

Reset-link aanpassen om project filter te tonen wanneer:
- Er een zoekterm of type filter actief is, OF
- Het geselecteerde project afwijkt van de context-default

```php
<?php
// Bepaal of project expliciet gewijzigd is ten opzichte van context
$contextDefaultProjectId = $isAllProjects
    ? \app\components\ProjectContext::ALL_PROJECTS_ID
    : ($currentProject?->id);
$projectChanged = $searchModel->project_id !== $contextDefaultProjectId;
?>
<?php if ($searchModel->q !== null || $searchModel->type !== null || $projectChanged): ?>
    <?= Html::a('Reset', ['index', 'show_all' => $showChildren ? 1 : null], ['class' => 'btn btn-link px-2']) ?>
<?php endif; ?>
```

**Opmerking over reset-gedrag:** De reset-link navigeert naar `index` zonder `project_id` parameter. Het model zal dan `project_id` defaulten naar de context (via Stap 1 logica), waardoor de dropdown correct reset.

### Stap 4: Fixture data uitbreiden

**Bestanden:**
- `yii/tests/fixtures/data/projects.php` — tweede project toevoegen voor user 100
- `yii/tests/fixtures/data/notes.php` — note toevoegen voor user 100 in project 2

De huidige fixtures hebben slechts 1 project voor user 100. Om de "All Projects" test zinvol te maken, moet er een tweede project zijn.

**Voeg toe aan `projects.php`:**

```php
'project4' => [
    'id' => 4,
    'user_id' => 100,
    'name' => 'Second Test Project',
    'label' => 'STP',
    'description' => 'Second project for user 100',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
],
```

**Voeg toe aan `notes.php`:**

```php
[
    'id' => 7,
    'user_id' => 100,
    'project_id' => 4,
    'parent_id' => null,
    'name' => 'Note in Second Project',
    'type' => 'note',
    'content' => '{"ops":[{"insert":"Note in second project\\n"}]}',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
],
```

### Stap 5: Unit test

**Bestand:** `yii/tests/unit/models/NoteSearchTest.php`

Voeg de volgende tests toe. Let op: Codeception Unit tests gebruiken `$this->getFixture()`, niet `$this->tester->grabFixture()`.

```php
use app\components\ProjectContext;

public function testSearchFiltersOnProjectId(): void
{
    $searchModel = new NoteSearch();

    // Act: zoek met specifiek project (form submit simuleert dit)
    $result = $searchModel->search(
        ['NoteSearch' => ['project_id' => 1]],
        100,           // userId
        null,          // currentProjectId
        true,          // isAllProjects (context)
        false          // showChildren
    );

    // Assert: alleen notes van project 1
    $models = $result->getModels();
    $this->assertNotEmpty($models, 'Expected notes in project 1');
    foreach ($models as $model) {
        $this->assertSame(1, $model->project_id);
    }
}

public function testSearchWithAllProjectsShowsAllUserNotes(): void
{
    $searchModel = new NoteSearch();
    $result = $searchModel->search(
        ['NoteSearch' => ['project_id' => ProjectContext::ALL_PROJECTS_ID]],
        100,
        null,
        false,   // isAllProjects = false, maar form override naar ALL
        false
    );

    // Assert: notes van meerdere projecten aanwezig (project 1 en 4 voor user 100)
    $projectIds = array_unique(array_map(fn($m) => $m->project_id, $result->getModels()));
    // Filter nulls (global notes)
    $projectIds = array_filter($projectIds, fn($id) => $id !== null);
    $this->assertGreaterThan(1, count($projectIds), 'Expected notes from multiple projects');
}

public function testSearchDefaultsToContextProject(): void
{
    $searchModel = new NoteSearch();

    // Act: geen project_id in params, default naar context project
    $result = $searchModel->search(
        [],                    // geen form data
        100,
        1,                     // context default = project 1
        false,                 // niet "all projects"
        false
    );

    // Assert: alleen notes van project 1 EN model heeft project_id gezet
    $this->assertSame(1, $searchModel->project_id, 'Model should have defaulted project_id');
    $models = $result->getModels();
    foreach ($models as $model) {
        $this->assertSame(1, $model->project_id);
    }
}

public function testSearchDefaultsToAllProjectsWhenContextIsAll(): void
{
    $searchModel = new NoteSearch();

    // Act: geen form data, context = all projects
    $result = $searchModel->search(
        [],
        100,
        null,
        true,    // isAllProjects
        false
    );

    // Assert: model heeft ALL_PROJECTS_ID
    $this->assertSame(ProjectContext::ALL_PROJECTS_ID, $searchModel->project_id);
}
```

## Verificatie

```bash
# Run unit tests (nieuwe tests)
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php:testSearchFiltersOnProjectId
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php:testSearchWithAllProjectsShowsAllUserNotes
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php:testSearchDefaultsToContextProject
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php:testSearchDefaultsToAllProjectsWhenContextIsAll

# Run alle NoteSearch tests
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php

# Linter check
cd /var/www/html/yii && vendor/bin/php-cs-fixer fix models/NoteSearch.php --config=../.php-cs-fixer.dist.php --dry-run
cd /var/www/html/yii && vendor/bin/php-cs-fixer fix controllers/NoteController.php --config=../.php-cs-fixer.dist.php --dry-run
```

### Handmatige testscenario's

| # | Scenario | Verwacht resultaat |
|---|----------|-------------------|
| 1 | Open `/note/index` met context = specifiek project | Dropdown toont dat project als geselecteerd |
| 2 | Open `/note/index` met context = "All Projects" | Dropdown toont "All Projects" als geselecteerd |
| 3 | Selecteer ander project in dropdown, klik Search | Notes gefilterd op geselecteerd project |
| 4 | Selecteer "All Projects", klik Search | Notes van alle eigen projecten zichtbaar |
| 5 | Met filter actief, klik Reset | Dropdown reset naar context-default |
| 6 | Combineer project filter met zoekterm en type | Alle filters werken samen (AND) |
| 7 | Pagineer met project filter actief | Paginering behoudt filter |

## Bestanden overzicht

| Bestand | Actie | Beschrijving |
|---------|-------|--------------|
| `yii/models/NoteSearch.php` | Wijzigen | `project_id` attribuut, import `ProjectContext`, logica voor default |
| `yii/controllers/NoteController.php` | Wijzigen | `$projectOptions` variabele, `isAllProjects` berekening |
| `yii/views/note/index.php` | Wijzigen | Project dropdown, reset-conditie met context-vergelijking |
| `yii/tests/unit/models/NoteSearchTest.php` | Wijzigen | 4 tests voor projectfiltering |
| `yii/tests/fixtures/data/projects.php` | Wijzigen | Tweede project toevoegen voor user 100 |
| `yii/tests/fixtures/data/notes.php` | Wijzigen | Note toevoegen in tweede project voor user 100 |

## UI Mockup

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Note List                                                                   │
│ ┌────────────────────────┐ ┌───────────────┐ ┌───────────────┐ ┌──────────┐│
│ │ Search notes...        │ │ All Types  ▼  │ │ MyProject  ▼  │ │ Search   ││
│ └────────────────────────┘ └───────────────┘ └───────────────┘ │ Reset    ││
│                                                                 └──────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
```

## Referenties

- `yii/models/ContextSearch.php` — Voorbeeld van search model met project filtering
- `yii/components/ProjectContext.php` — `ALL_PROJECTS_ID = -1` constante
- `yii/views/note/index.php` — Huidige zoekformulier structuur
- `yii/controllers/NoteController.php:96-118` — Huidige `actionIndex` implementatie
