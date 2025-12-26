<?php

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\PromptTemplateSearch;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;

class PromptTemplateSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'promptTemplates' => PromptTemplateFixture::class,
        ];
    }

    public function testSearchWithNoProjectIdReturnsOnlyTemplatesWithNullProject(): void
    {
        $searchModel = new PromptTemplateSearch();

        $dataProvider = $searchModel->search([], 100, ProjectContext::NO_PROJECT_ID);

        // Since all fixture templates have a project_id, result should be empty
        $this->assertSame(0, $dataProvider->getTotalCount());
    }

    public function testSearchWithNullProjectIdReturnsAllUserTemplates(): void
    {
        $searchModel = new PromptTemplateSearch();

        // User 100 owns project 1, which has template 1
        $dataProvider = $searchModel->search([], 100);

        $this->assertSame(1, $dataProvider->getTotalCount());
    }

    public function testSearchWithSpecificProjectIdReturnsOnlyThatProjectTemplates(): void
    {
        $searchModel = new PromptTemplateSearch();

        // User 100, project 1 has template 1
        $dataProvider = $searchModel->search([], 100, 1);

        $this->assertSame(1, $dataProvider->getTotalCount());
        $models = $dataProvider->getModels();
        $this->assertSame(1, $models[0]->id);
    }

    public function testSearchWithDifferentUserReturnsTheirTemplates(): void
    {
        $searchModel = new PromptTemplateSearch();

        // User 1 owns projects 2 and 3, project 2 has template 2
        $dataProvider = $searchModel->search([], 1);

        $this->assertSame(1, $dataProvider->getTotalCount());
        $models = $dataProvider->getModels();
        $this->assertSame(2, $models[0]->id);
    }
}
