<?php

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\PromptInstanceSearch;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptInstanceFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;

class PromptInstanceSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'promptTemplates' => PromptTemplateFixture::class,
            'promptInstances' => PromptInstanceFixture::class,
        ];
    }

    public function testSearchWithNoProjectIdReturnsOnlyInstancesWithNullTemplateProject(): void
    {
        $searchModel = new PromptInstanceSearch();

        $dataProvider = $searchModel->search([], 100, ProjectContext::NO_PROJECT_ID);

        // Since all fixture instances have templates with project_id, result should be empty
        $this->assertSame(0, $dataProvider->getTotalCount());
    }

    public function testSearchWithNullProjectIdReturnsAllUserInstances(): void
    {
        $searchModel = new PromptInstanceSearch();

        // User 100 owns project 1, which has template 1, which has instance 1
        $dataProvider = $searchModel->search([], 100);

        $this->assertSame(1, $dataProvider->getTotalCount());
    }

    public function testSearchWithSpecificProjectIdReturnsOnlyThatProjectInstances(): void
    {
        $searchModel = new PromptInstanceSearch();

        // User 100, project 1 has template 1 which has instance 1
        $dataProvider = $searchModel->search([], 100, 1);

        $this->assertSame(1, $dataProvider->getTotalCount());
        $models = $dataProvider->getModels();
        $this->assertSame(1, $models[0]->id);
    }

    public function testSearchWithDifferentProjectReturnsNoInstances(): void
    {
        $searchModel = new PromptInstanceSearch();

        // User 100 owns project 1 only; no instances exist for other projects they own
        $dataProvider = $searchModel->search([], 100, 999);

        $this->assertSame(0, $dataProvider->getTotalCount());
    }
}
