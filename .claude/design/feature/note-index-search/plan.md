# Technisch Plan: Note Index Search

## Aanpak

Yii2 biedt een standaard pattern voor filtering in GridView via `filterModel`. Dit integreert automatisch met paginering en sortering. We gebruiken een custom zoekformulier boven de GridView omdat clickable rows conflicteren met column filters.

## Gekozen aanpak

1. Custom formulier BOVEN de GridView als compact horizontaal formulier
2. GridView zonder `filterModel` (geen filters in kolomheaders)
3. Hergebruik bestaande `NoteQuery::searchByTerm()` scope voor gecombineerd zoeken

Dit volgt het Yii2 pattern maar met aangepaste UI, consistent met de clickable row pattern in deze view.

## Implementatie stappen

1. `NoteSearch.php` aanpassen — `searchTerm` property toevoegen
2. `index.php` view aanpassen — zoekformulier + toggle parameter behoud
3. `NoteFixture.php` + fixture data aanmaken
4. `NoteSearchTest.php` unit tests schrijven
5. Linter + tests draaien

## Wijzigingen

### 1. NoteSearch model uitbreiden

**Bestand:** `yii/models/NoteSearch.php`

**Wijzigingen:**
1. Voeg `searchTerm` property toe (nullable string voor gecombineerd zoeken)
2. Update `rules()` om `searchTerm` safe te maken
3. Vervang `andFilterWhere(['like', 'name', ...])` door `searchByTerm()` scope

**Volledige gewijzigde class:**

```php
<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * NoteSearch represents the model behind the search form about `app\models\Note`.
 *
 * @property string|null $searchTerm Gecombineerde zoekterm voor naam en content
 */
class NoteSearch extends Note
{
    public ?string $searchTerm = null;

    public function rules(): array
    {
        return [
            [['type', 'searchTerm'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(
        array $params,
        int $userId,
        ?int $currentProjectId = null,
        bool $isAllProjects = false,
        bool $showChildren = false
    ): ActiveDataProvider {
        $query = $isAllProjects
            ? Note::find()->forUser($userId)
            : Note::find()->forUserWithProject($userId, $currentProjectId);

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

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Gecombineerd zoeken in naam en content via bestaande query scope
        if ($this->searchTerm !== null && $this->searchTerm !== '') {
            $query->searchByTerm($this->searchTerm);
        }

        // Type filter
        $query->andFilterWhere(['type' => $this->type]);

        return $dataProvider;
    }
}
```

**Let op:** `name` is verwijderd uit rules omdat we nu `searchTerm` gebruiken voor gecombineerd zoeken.

### 2. View aanpassen

**Bestand:** `yii/views/note/index.php`

**Toevoegen import (na regel 14, bij andere use statements):**
```php
use yii\bootstrap5\ActiveForm;
```

**Verwijderen (regel 57-63):**
```php
    <div class="mb-3">
        <?php if ($showChildren): ?>
            <?= Html::a('Hide children', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?php else: ?>
            <?= Html::a('Show all', ['index', 'show_all' => 1], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?php endif; ?>
    </div>
```

**Vervangen door (op dezelfde locatie, vóór de card op regel 65):**

**Toevoegen zoekformulier + aanpassen toggle:**

```php
<?php
// Filter state voor toggle links
$searchParams = [];
if ($searchModel->searchTerm !== null && $searchModel->searchTerm !== '') {
    $searchParams['NoteSearch']['searchTerm'] = $searchModel->searchTerm;
}
if ($searchModel->type !== null && $searchModel->type !== '') {
    $searchParams['NoteSearch']['type'] = $searchModel->type;
}
$hasFilters = !empty($searchModel->searchTerm) || !empty($searchModel->type);
?>

<div class="mb-3">
    <?php if ($showChildren): ?>
        <?= Html::a('Hide children', array_merge(['index'], $searchParams), [
            'class' => 'btn btn-sm btn-outline-secondary',
        ]) ?>
    <?php else: ?>
        <?= Html::a('Show all', array_merge(['index'], $searchParams, ['show_all' => 1]), [
            'class' => 'btn btn-sm btn-outline-secondary',
        ]) ?>
    <?php endif; ?>
</div>

<?php $form = ActiveForm::begin([
    'action' => ['index'],
    'method' => 'get',
    'options' => ['class' => 'row g-2 mb-4 align-items-end'],
]); ?>

<div class="col-md-5">
    <?= $form->field($searchModel, 'searchTerm')->textInput([
        'placeholder' => 'Search in name and content...',
        'class' => 'form-control',
        'aria-label' => 'Search notes',
    ])->label(false) ?>
</div>

<div class="col-md-3">
    <?= $form->field($searchModel, 'type')->dropDownList(
        NoteType::labels(),
        [
            'prompt' => 'All Types',
            'class' => 'form-select',
            'aria-label' => 'Filter by type',
        ]
    )->label(false) ?>
</div>

<?php if ($showChildren): ?>
    <?= Html::hiddenInput('show_all', 1) ?>
<?php endif; ?>

<div class="col-md-4 d-flex gap-2">
    <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
    <?php if ($hasFilters): ?>
        <?= Html::a('Reset', $showChildren ? ['index', 'show_all' => 1] : ['index'], [
            'class' => 'btn btn-outline-secondary',
        ]) ?>
    <?php else: ?>
        <?= Html::tag('span', 'Reset', [
            'class' => 'btn btn-outline-secondary disabled',
            'aria-disabled' => 'true',
        ]) ?>
    <?php endif; ?>
</div>

<?php ActiveForm::end(); ?>
```



## Bestandswijzigingen

| Bestand | Wijziging |
|---------|-----------|
| `yii/models/NoteSearch.php` | `searchTerm` attribuut + rules + search() aanpassing |
| `yii/views/note/index.php` | Zoekformulier + toggle parameter behoud |
| `yii/tests/fixtures/NoteFixture.php` | Nieuw fixture bestand |
| `yii/tests/fixtures/data/notes.php` | Fixture data |
| `yii/tests/unit/models/NoteSearchTest.php` | Unit tests |

## Nieuwe bestanden

| Bestand | Doel |
|---------|------|
| `yii/tests/fixtures/NoteFixture.php` | ActiveFixture voor Note model |
| `yii/tests/fixtures/data/notes.php` | Test data voor notes |
| `yii/tests/unit/models/NoteSearchTest.php` | Unit tests voor zoekfunctionaliteit |

## Hergebruikte componenten
- `NoteSearch` (al aanwezig)
- `NoteQuery::searchByTerm()` (al aanwezig, wordt nu gebruikt)
- Bootstrap 5 form styling (al aanwezig)
- `yii\bootstrap5\ActiveForm` (standaard Yii2 widget)
- `common\enums\NoteType` (al aanwezig)

## Sortering

De huidige sortering (`updated_at DESC`) blijft default. Optioneel kunnen we sorteren op `name` toevoegen als de user dat wil, maar dat is buiten de huidige scope.

## Test scenario's

### Unit tests

**Vereiste fixtures:** NoteFixture moet worden aangemaakt voordat tests kunnen draaien.

**Bestand:** `yii/tests/fixtures/NoteFixture.php`

```php
<?php

namespace tests\fixtures;

use app\models\Note;
use yii\test\ActiveFixture;

class NoteFixture extends ActiveFixture
{
    public $modelClass = Note::class;
    public $dataFile = '@tests/fixtures/data/notes.php';
    public $depends = [
        UserFixture::class,
        ProjectFixture::class,
    ];
}
```

**Bestand:** `yii/tests/fixtures/data/notes.php`

```php
<?php

return [
    // Top-level notes for user 100, project 1
    'note1' => [
        'id' => 1,
        'user_id' => 100,
        'project_id' => 1,
        'parent_id' => null,
        'name' => 'Test Note Alpha',
        'content' => '{"ops":[{"insert":"Content with searchable text\\n"}]}',
        'type' => 'note',
        'created_at' => '2025-01-01 10:00:00',
        'updated_at' => '2025-01-01 10:00:00',
    ],
    'note2' => [
        'id' => 2,
        'user_id' => 100,
        'project_id' => 1,
        'parent_id' => null,
        'name' => 'Meeting Summary',
        'content' => '{"ops":[{"insert":"Another note without test keyword\\n"}]}',
        'type' => 'summation',
        'created_at' => '2025-01-02 10:00:00',
        'updated_at' => '2025-01-02 10:00:00',
    ],
    // Child note
    'note3' => [
        'id' => 3,
        'user_id' => 100,
        'project_id' => 1,
        'parent_id' => 1,
        'name' => 'Child Note',
        'content' => '{"ops":[{"insert":"Test child content\\n"}]}',
        'type' => 'note',
        'created_at' => '2025-01-03 10:00:00',
        'updated_at' => '2025-01-03 10:00:00',
    ],
    // Note for different project (user 1 owns project 2)
    'note4' => [
        'id' => 4,
        'user_id' => 1,
        'project_id' => 2,
        'parent_id' => null,
        'name' => 'Other Project Note with Test',
        'content' => '{"ops":[{"insert":"Different project\\n"}]}',
        'type' => 'import',
        'created_at' => '2025-01-04 10:00:00',
        'updated_at' => '2025-01-04 10:00:00',
    ],
];
```

**Let op:** Fixture data gebruikt alleen user_id's (100, 1) en project_id's (1, 2) die in de bestaande `user.php` en `projects.php` fixtures bestaan. De `testSearchExcludesOtherUsers` test is aangepast om te verifiëren dat user 100 geen notes van user 1 ziet.

**Bestand:** `yii/tests/unit/models/NoteSearchTest.php`

```php
<?php

namespace tests\unit\models;

use app\models\NoteSearch;
use Codeception\Test\Unit;
use tests\fixtures\NoteFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class NoteSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'notes' => NoteFixture::class,
        ];
    }

    public function testSearchByTermFiltersNameAndContent(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['searchTerm' => 'Test']],
            100,
            1,
            false,
            true // Include children to get note ID 3
        );

        $models = $dataProvider->getModels();

        // Should find: ID 1 (name match), ID 3 (content match)
        // Should NOT find: ID 2 (no match)
        $this->assertCount(2, $models);

        $ids = array_column($models, 'id');
        sort($ids);
        $this->assertSame([1, 3], $ids);
    }

    public function testSearchByTypeFilters(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['type' => 'summation']],
            100,
            1
        );

        $models = $dataProvider->getModels();

        $this->assertCount(1, $models);
        $this->assertSame('summation', $models[0]->type);
        $this->assertSame(2, $models[0]->id);
    }

    public function testSearchCombinesFilters(): void
    {
        $searchModel = new NoteSearch();

        // Search for 'Test' with type 'note' should find ID 1, exclude ID 2 (summation)
        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['searchTerm' => 'Test', 'type' => 'note']],
            100,
            1
        );

        $models = $dataProvider->getModels();

        $this->assertCount(1, $models);
        $this->assertSame('note', $models[0]->type);
        $this->assertSame(1, $models[0]->id);
    }

    public function testSearchWithEmptyTermReturnsAll(): void
    {
        $searchModel = new NoteSearch();

        $withTerm = $searchModel->search(['NoteSearch' => ['searchTerm' => '']], 100, 1);
        $withoutTerm = $searchModel->search([], 100, 1);

        $this->assertSame($withoutTerm->getTotalCount(), $withTerm->getTotalCount());
    }

    public function testSearchRespectsProjectContext(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search([], 100, 1);

        foreach ($dataProvider->getModels() as $note) {
            $this->assertSame(1, $note->project_id);
        }
    }

    public function testSearchExcludesOtherUsers(): void
    {
        $searchModel = new NoteSearch();

        // Search all projects for user 100 — should find notes 1, 2, 3 but NOT note 4 (user 1)
        $dataProvider = $searchModel->search([], 100, null, true, true);

        $models = $dataProvider->getModels();
        $this->assertCount(3, $models);

        $ids = array_column($models, 'id');
        sort($ids);
        $this->assertSame([1, 2, 3], $ids);

        foreach ($models as $note) {
            $this->assertSame(100, $note->user_id);
        }
    }

    public function testSearchWithShowChildrenIncludesChildren(): void
    {
        $searchModel = new NoteSearch();

        $withChildren = $searchModel->search([], 100, 1, false, true);
        $withoutChildren = $searchModel->search([], 100, 1, false, false);

        // Without children: ID 1, 2 (top-level only)
        // With children: ID 1, 2, 3
        $this->assertCount(2, $withoutChildren->getModels());
        $this->assertCount(3, $withChildren->getModels());
    }
}
```

### Handmatige verificatie

1. **Zoeken op naam**: Voer "test" in → alleen notes met "test" in naam of content
2. **Filteren op type**: Selecteer "Summation" → alleen summation notes
3. **Combinatie**: Zoekterm + type filter → beide criteria
4. **Paginering**: Zoeken met 15+ resultaten → paginering werkt correct
5. **Reset**: Klik reset-knop → alle filters gewist, alle notes zichtbaar
6. **Project context**: In specifiek project → zoekt alleen binnen dat project
7. **Show all toggle**: Met zoekfilter → child notes ook gefilterd, filters behouden
8. **Lege resultaten**: Zoekterm zonder matches → "No notes yet." melding
9. **URL parameters**: Na zoeken → filters zichtbaar in URL, terug-knop werkt

## Verificatie

```bash
# Run unit tests voor NoteSearch
docker exec pma_yii vendor/bin/codecept run unit tests/unit/models/NoteSearchTest.php

# Run alle unit tests
docker exec pma_yii vendor/bin/codecept run unit

# Lint check
./linter.sh fix
```

## Rollback

Geen database-wijzigingen, alleen view- en model-aanpassingen. Rollback = git revert.

## Referenties

- `yii/models/query/NoteQuery.php:74-80` — `searchByTerm()` scope implementatie
- `yii/models/ContextSearch.php` — Vergelijkbaar search model pattern
- `yii/tests/unit/models/ContextSearchTest.php` — Test structuur voorbeeld
- `common/enums/NoteType.php` — Type enum met `labels()` method
- `yii/views/note/index.php` — Huidige view structuur
