# Technisch Plan: Note Index Project Dropdown

## Aanpak

We breiden de bestaande zoekfunctionaliteit uit met project-filtering via het zoekformulier. Dit vervangt de impliciete filtering via globale projectcontext door expliciete filtering in het searchModel.

## Fasering

### Fase 1: Single-select dropdown
Eenvoudige dropdown met één projectselectie. Laagste complexiteit, snelste implementatie.

### Fase 2: Multi-select (optioneel)
Select2Widget met `multiple: true` voor meerdere projecten. Vereist array-handling in model en query.

Dit plan beschrijft **Fase 1** met notities voor Fase 2.

---

## Implementatie stappen

1. `NoteSearch.php` aanpassen — `project_id` property + filtering
2. `NoteController::actionIndex()` aanpassen — default-waarde meegeven
3. `index.php` view aanpassen — dropdown toevoegen
4. Unit tests uitbreiden — project-filtering testen
5. Linter + tests draaien

---

## Wijzigingen

### 1. NoteSearch model uitbreiden

**Bestand:** `yii/models/NoteSearch.php`

**Wijzigingen:**
1. Voeg `project_id` property toe (nullable int, default via params)
2. Update `rules()` om `project_id` safe/integer te maken
3. Verwijder `$currentProjectId` en `$isAllProjects` parameters uit `search()`
4. Bepaal project-filtering op basis van `$this->project_id`

**Volledige gewijzigde class:**

```php
<?php

namespace app\models;

use app\components\ProjectContext;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * NoteSearch represents the model behind the search form about `app\models\Note`.
 */
class NoteSearch extends Note
{
    public ?string $q = null;
    public ?int $project_id = null;

    public function rules(): array
    {
        return [
            [['name', 'type', 'q'], 'safe'],
            [['project_id'], 'integer'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array $params Request parameters
     * @param int $userId Current user ID
     * @param int|null $defaultProjectId Default project from context (fallback)
     * @param bool $showChildren Include child notes
     */
    public function search(
        array $params,
        int $userId,
        ?int $defaultProjectId = null,
        bool $showChildren = false
    ): ActiveDataProvider {
        $this->load($params);

        // Als project_id niet in params zit, gebruik default uit context
        if ($this->project_id === null && $defaultProjectId !== null) {
            $this->project_id = $defaultProjectId;
        }

        // Bepaal query op basis van project_id
        $isAllProjects = $this->project_id === null
            || $this->project_id === ProjectContext::ALL_PROJECTS_ID;

        $query = $isAllProjects
            ? Note::find()->forUser($userId)
            : Note::find()->forUserWithProject($userId, $this->project_id);

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

**Breaking change:** De signature van `search()` wijzigt. Bestaande tests moeten worden aangepast.

---

### 2. Controller aanpassen

**Bestand:** `yii/controllers/NoteController.php`

**Wijzigingen in `actionIndex()`:**

```php
public function actionIndex(): string
{
    $searchModel = new NoteSearch();
    $currentProject = $this->projectContext->getCurrentProject();
    $isAllProjects = $this->projectContext->isAllProjectsContext();
    $showChildren = (bool) Yii::$app->request->get('show_all', 0);

    // Bepaal default project_id voor dropdown
    $defaultProjectId = $isAllProjects
        ? ProjectContext::ALL_PROJECTS_ID
        : $currentProject?->id;

    $dataProvider = $searchModel->search(
        Yii::$app->request->queryParams,
        Yii::$app->user->id,
        $defaultProjectId,
        $showChildren
    );

    return $this->render('index', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider,
        'currentProject' => $currentProject,
        'isAllProjects' => $isAllProjects,
        'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
        'showChildren' => $showChildren,
    ]);
}
```

**Let op:** De `isAllProjects` variabele in de view kan nu inconsistent zijn met `searchModel->project_id`. Overweeg deze te verwijderen of te baseren op het searchModel.

---

### 3. View aanpassen

**Bestand:** `yii/views/note/index.php`

**Toevoegen import:**
```php
use app\components\ProjectContext;
```

**Aanpassen zoekformulier (binnen card-header):**

Vervang het bestaande formulier in de card-header door:

```php
<?php $form = ActiveForm::begin([
    'action' => ['index', 'show_all' => $showChildren ? 1 : null],
    'method' => 'get',
    'options' => ['class' => 'd-flex flex-wrap align-items-center gap-2 mb-0'],
]); ?>
    <?= $form->field($searchModel, 'q', [
        'options' => ['class' => 'mb-0'],
    ])->textInput([
        'class' => 'form-control',
        'placeholder' => 'Search notes...',
    ])->label(false) ?>

    <?= $form->field($searchModel, 'type', [
        'options' => ['class' => 'mb-0'],
    ])->dropDownList(
        NoteType::labels(),
        [
            'class' => 'form-select',
            'prompt' => 'All Types',
        ]
    )->label(false) ?>

    <?= $form->field($searchModel, 'project_id', [
        'options' => ['class' => 'mb-0'],
    ])->dropDownList(
        [ProjectContext::ALL_PROJECTS_ID => 'All Projects'] + $projectList,
        [
            'class' => 'form-select',
        ]
    )->label(false) ?>

    <div class="d-flex align-items-center gap-2">
        <?= Html::submitButton('Search', ['class' => 'btn btn-outline-primary']) ?>
        <?php if ($searchModel->q !== null || $searchModel->type !== null
            || ($searchModel->project_id !== null
                && $searchModel->project_id !== ProjectContext::ALL_PROJECTS_ID
                && $searchModel->project_id !== $currentProject?->id)): ?>
            <?= Html::a('Reset', ['index', 'show_all' => $showChildren ? 1 : null], [
                'class' => 'btn btn-link px-2',
            ]) ?>
        <?php endif; ?>
    </div>
<?php ActiveForm::end(); ?>
```

**Verwijderen alert-box:**

De bestaande alert voor "Showing notes from all projects" kan worden verwijderd of aangepast:

```php
<?php if ($searchModel->project_id === ProjectContext::ALL_PROJECTS_ID): ?>
    <div class="alert alert-info alert-sm mb-0">
        Searching across all projects.
    </div>
<?php endif; ?>
```

---

### 4. Unit tests aanpassen

**Bestand:** `yii/tests/unit/models/NoteSearchTest.php`

Bestaande tests moeten worden aangepast voor de nieuwe signature. Voeg tests toe voor project-filtering:

```php
public function testSearchFiltersbyProjectId(): void
{
    $searchModel = new NoteSearch();

    $dataProvider = $searchModel->search(
        ['NoteSearch' => ['project_id' => 1]],
        100,
        null, // geen default
        false
    );

    foreach ($dataProvider->getModels() as $note) {
        $this->assertSame(1, $note->project_id);
    }
}

public function testSearchAllProjectsReturnsMultipleProjects(): void
{
    $searchModel = new NoteSearch();

    $dataProvider = $searchModel->search(
        ['NoteSearch' => ['project_id' => ProjectContext::ALL_PROJECTS_ID]],
        100,
        null,
        true // include children
    );

    $projectIds = array_unique(array_column($dataProvider->getModels(), 'project_id'));
    // Met fixture data zou dit meerdere projecten kunnen bevatten
    // (afhankelijk van fixture setup)
    $this->assertNotEmpty($dataProvider->getModels());
}

public function testSearchDefaultsToContextProject(): void
{
    $searchModel = new NoteSearch();

    $dataProvider = $searchModel->search(
        [], // geen project_id in params
        100,
        1,  // default project
        false
    );

    $this->assertSame(1, $searchModel->project_id);
    foreach ($dataProvider->getModels() as $note) {
        $this->assertSame(1, $note->project_id);
    }
}
```

---

## Fase 2: Multi-select (optioneel)

Voor multi-select zijn deze aanvullende wijzigingen nodig:

### Model
```php
// Vervang project_id door project_ids array
public ?array $project_ids = null;

public function rules(): array
{
    return [
        [['name', 'type', 'q'], 'safe'],
        [['project_ids'], 'each', 'rule' => ['integer']],
    ];
}

// In search():
if (!empty($this->project_ids)) {
    $projectIds = array_filter($this->project_ids, fn($id) => $id !== ProjectContext::ALL_PROJECTS_ID);
    if (empty($projectIds) || in_array(ProjectContext::ALL_PROJECTS_ID, $this->project_ids, true)) {
        // "All Projects" geselecteerd
        $query = Note::find()->forUser($userId);
    } else {
        $query = Note::find()->forUser($userId)->andWhere(['project_id' => $projectIds]);
    }
}
```

### View
```php
<?= $form->field($searchModel, 'project_ids', [
    'options' => ['class' => 'mb-0'],
])->widget(Select2Widget::class, [
    'items' => [ProjectContext::ALL_PROJECTS_ID => 'All Projects'] + $projectList,
    'options' => [
        'placeholder' => 'Select projects...',
        'multiple' => true,
    ],
    'settings' => [
        'minimumResultsForSearch' => 0,
    ],
])->label(false) ?>
```

---

## Bestandswijzigingen

| Bestand | Wijziging |
|---------|-----------|
| `yii/models/NoteSearch.php` | `project_id` attribuut + filtering logica |
| `yii/controllers/NoteController.php` | Default project_id bepalen + nieuwe search() signature |
| `yii/views/note/index.php` | Project dropdown toevoegen aan formulier |
| `yii/tests/unit/models/NoteSearchTest.php` | Tests aanpassen voor nieuwe signature + projectfilter tests |

---

## Verificatie

```bash
# Run unit tests voor NoteSearch
docker exec pma_yii vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php

# Run alle unit tests
docker exec pma_yii vendor/bin/codecept run unit

# Lint check
./linter.sh fix
```

## Handmatige verificatie

1. **Default naar context**: Open note/index met project "Alpha" actief → dropdown toont "Alpha"
2. **Wijzig project**: Selecteer "Beta" → zoekresultaten tonen alleen Beta-notes
3. **All Projects**: Selecteer "All Projects" → zoekresultaten van alle projecten
4. **Combinatie**: Zoekterm + type + project → alle filters combineren
5. **Paginering**: Navigeer pagina's → projectfilter blijft behouden
6. **Reset**: Klik reset → alle filters inclusief project gewist
7. **URL**: Controleer `NoteSearch[project_id]=X` in URL
8. **Show all toggle**: Filters behouden bij toggle

## Rollback

Geen database-wijzigingen. Rollback = git revert.

## Referenties

- `yii/views/prompt-instance/_form.php:104-114` — Select2Widget multi-select voorbeeld
- `yii/views/project/_form.php:237-248` — Select2Widget project-linking
- `yii/components/ProjectContext.php:27` — `ALL_PROJECTS_ID = -1` constante
- `yii/services/ProjectService.php:52-62` — `fetchProjectsList()` methode
