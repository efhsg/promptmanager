<?php

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\FieldSearch;
use Codeception\Test\Unit;
use tests\fixtures\FieldFixture;
use tests\fixtures\FieldOptionFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class FieldSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'fields' => FieldFixture::class,
            'fieldOptions' => FieldOptionFixture::class,
        ];
    }

    public function testSearchWithNoProjectIdReturnsOnlyFieldsWithNullProject(): void
    {
        $searchModel = new FieldSearch();

        // User 1 has fields 1 and 3 with null project_id
        $dataProvider = $searchModel->search([], 1, ProjectContext::NO_PROJECT_ID);

        $this->assertSame(2, $dataProvider->getTotalCount());

        $models = $dataProvider->getModels();
        foreach ($models as $model) {
            $this->assertNull($model->project_id, 'All returned fields should have null project_id');
        }
    }

    public function testSearchWithNoProjectIdForUser100ReturnsOnlyTheirNullProjectFields(): void
    {
        $searchModel = new FieldSearch();

        // User 100 has field 4 with null project_id
        $dataProvider = $searchModel->search([], 100, ProjectContext::NO_PROJECT_ID);

        $this->assertSame(1, $dataProvider->getTotalCount());
        $models = $dataProvider->getModels();
        $this->assertSame(4, $models[0]->id);
    }

    public function testSearchWithNullProjectIdReturnsAllUserFields(): void
    {
        $searchModel = new FieldSearch();

        // User 1 has fields 1, 2, 3, 5, 6 (all their fields)
        $dataProvider = $searchModel->search([], 1, null);

        $this->assertSame(5, $dataProvider->getTotalCount());
    }

    public function testSearchWithSpecificProjectIdReturnsOnlyThatProjectFields(): void
    {
        $searchModel = new FieldSearch();

        // User 1, project 3 has fields 5, 6
        $dataProvider = $searchModel->search([], 1, 3);

        $this->assertSame(2, $dataProvider->getTotalCount());
    }
}
