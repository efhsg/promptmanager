<?php

namespace tests\unit\models;

use app\components\ProjectContext;
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

    public function testSearchByTermFindsMatchInName(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'Meeting']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
        $this->assertSame('Meeting Notes', $dataProvider->getModels()[0]->name);
    }

    public function testSearchByTermFindsMatchInContent(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'API design']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
        $this->assertSame('Meeting Notes', $dataProvider->getModels()[0]->name);
    }

    public function testSearchByTermIsCaseInsensitive(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'meeting']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
    }

    public function testSearchByTypeFiltersSummation(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['type' => 'summation']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
        $this->assertSame('Project Summary', $dataProvider->getModels()[0]->name);
    }

    public function testSearchByTypeFiltersImport(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['type' => 'import']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
        $this->assertSame('YouTube Transcript', $dataProvider->getModels()[0]->name);
    }

    public function testSearchCombinesTermAndTypeFilter(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'project', 'type' => 'summation']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(1, $dataProvider->getTotalCount());
        $this->assertSame('Project Summary', $dataProvider->getModels()[0]->name);
    }

    public function testSearchCombinesTermAndTypeFilterNoMatch(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'Meeting', 'type' => 'summation']],
            100,
            1,
            false,
            true
        );

        $this->assertSame(0, $dataProvider->getTotalCount());
    }

    public function testSearchRespectsProjectContext(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            [],
            100,
            1,
            false,
            true
        );

        // Verify all returned notes belong to project 1
        $models = $dataProvider->getModels();
        $this->assertGreaterThan(0, count($models), 'Should return notes for project 1');
        foreach ($models as $model) {
            $this->assertSame(1, $model->project_id);
            $this->assertSame(100, $model->user_id);
        }
    }

    public function testSearchTopLevelOnlyExcludesChildren(): void
    {
        $searchModel = new NoteSearch();

        // With children
        $dataProviderWithChildren = $searchModel->search(
            [],
            100,
            1,
            false,
            true
        );
        $countWithChildren = $dataProviderWithChildren->getTotalCount();

        // Without children (top-level only)
        $searchModel2 = new NoteSearch();
        $dataProviderTopLevel = $searchModel2->search(
            [],
            100,
            1,
            false,
            false
        );
        $countTopLevel = $dataProviderTopLevel->getTotalCount();

        // Top-level should have fewer or equal notes
        $this->assertLessThanOrEqual($countWithChildren, $countTopLevel);

        // Verify all returned notes have no parent
        foreach ($dataProviderTopLevel->getModels() as $model) {
            $this->assertNull($model->parent_id, 'Top-level notes should have null parent_id');
        }
    }

    public function testSearchAllProjectsIncludesGlobalNotes(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            [],
            100,
            null,
            true,
            true
        );

        // Verify all returned notes belong to user 100
        $models = $dataProvider->getModels();
        $this->assertGreaterThan(0, count($models), 'Should return notes for user');
        foreach ($models as $model) {
            $this->assertSame(100, $model->user_id);
        }
    }

    public function testSearchEmptyTermReturnsAllNotes(): void
    {
        $searchModel = new NoteSearch();

        // Search with empty term
        $dataProviderWithEmptyTerm = $searchModel->search(
            ['NoteSearch' => ['q' => '']],
            100,
            1,
            false,
            true
        );

        // Search without term
        $searchModel2 = new NoteSearch();
        $dataProviderNoTerm = $searchModel2->search(
            [],
            100,
            1,
            false,
            true
        );

        // Empty term should return same as no term
        $this->assertSame(
            $dataProviderNoTerm->getTotalCount(),
            $dataProviderWithEmptyTerm->getTotalCount()
        );
    }

    public function testSearchDoesNotReturnOtherUsersNotes(): void
    {
        $searchModel = new NoteSearch();

        $dataProvider = $searchModel->search(
            ['NoteSearch' => ['q' => 'Other User']],
            100,
            null,
            true,
            true
        );

        // Note 6 belongs to user 1, should not be returned for user 100
        $this->assertSame(0, $dataProvider->getTotalCount());
    }

    public function testSearchFiltersOnProjectId(): void
    {
        $searchModel = new NoteSearch();

        // Act: search with specific project (form submit simulates this)
        $result = $searchModel->search(
            ['NoteSearch' => ['project_id' => 1]],
            100,
            null,
            true,
            false
        );

        // Assert: only notes from project 1
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
            false,
            false
        );

        // Assert: notes from multiple projects present (project 1 and 4 for user 100)
        $projectIds = array_unique(array_map(fn($m) => $m->project_id, $result->getModels()));
        // Filter nulls (global notes)
        $projectIds = array_filter($projectIds, fn($id) => $id !== null);
        $this->assertGreaterThan(1, count($projectIds), 'Expected notes from multiple projects');
    }

    public function testSearchDefaultsToContextProject(): void
    {
        $searchModel = new NoteSearch();

        // Act: no project_id in params, default to context project
        $result = $searchModel->search(
            [],
            100,
            1,
            false,
            false
        );

        // Assert: only notes from project 1 AND model has project_id set
        $this->assertSame(1, $searchModel->project_id, 'Model should have defaulted project_id');
        $models = $result->getModels();
        foreach ($models as $model) {
            $this->assertSame(1, $model->project_id);
        }
    }

    public function testSearchDefaultsToAllProjectsWhenContextIsAll(): void
    {
        $searchModel = new NoteSearch();

        // Act: no form data, context = all projects
        $searchModel->search(
            [],
            100,
            null,
            true,
            false
        );

        // Assert: model has ALL_PROJECTS_ID
        $this->assertSame(ProjectContext::ALL_PROJECTS_ID, $searchModel->project_id);
    }
}
