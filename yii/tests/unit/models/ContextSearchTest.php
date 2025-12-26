<?php

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\ContextSearch;
use Codeception\Test\Unit;
use tests\fixtures\ContextFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ContextSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
        ];
    }

    public function testSearchWithNoProjectIdReturnsOnlyContextsWithNullProject(): void
    {
        $searchModel = new ContextSearch();

        $dataProvider = $searchModel->search([], 100, ProjectContext::NO_PROJECT_ID);

        // Since all fixture contexts have a project_id, result should be empty
        $this->assertSame(0, $dataProvider->getTotalCount());
    }

    public function testSearchWithNullProjectIdReturnsAllUserContexts(): void
    {
        $searchModel = new ContextSearch();

        $dataProvider = $searchModel->search([], 100);

        // User 100 owns project 1, which has contexts 1 and 3
        $this->assertSame(2, $dataProvider->getTotalCount());
    }

    public function testSearchWithSpecificProjectIdReturnsOnlyThatProjectContexts(): void
    {
        $searchModel = new ContextSearch();

        $dataProvider = $searchModel->search([], 100, 1);

        // Project 1 has contexts 1 and 3
        $this->assertSame(2, $dataProvider->getTotalCount());
    }

    public function testSearchWithAllProjectsIdBehavesLikeNull(): void
    {
        $searchModel = new ContextSearch();

        // When controller detects ALL_PROJECTS, it passes null
        $dataProvider = $searchModel->search([], 100);

        // User 100 owns project 1, which has contexts 1 and 3
        $this->assertSame(2, $dataProvider->getTotalCount());
    }
}
