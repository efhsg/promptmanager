<?php

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\AiRun;
use app\models\AiRunSearch;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class AiRunSearchTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    protected function _before(): void
    {
        AiRun::deleteAll([]);
    }

    public function testSearchReturnsOnlyRunsForUser(): void
    {
        $this->createRun(100, 'My prompt');
        $this->createRun(100, 'Another prompt');
        $this->createRun(1, 'Other user prompt');

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([], 100);

        verify($dataProvider->getTotalCount())->equals(2);
    }

    public function testSearchFiltersOnStatus(): void
    {
        $this->createRun(100, 'Pending run', AiRunStatus::PENDING);
        $this->createRun(100, 'Completed run', AiRunStatus::COMPLETED);
        $this->createRun(100, 'Failed run', AiRunStatus::FAILED);

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([
            'AiRunSearch' => ['status' => 'completed'],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(1);
    }

    public function testSearchFiltersOnPromptSummary(): void
    {
        $this->createRun(100, 'Refactor authentication');
        $this->createRun(100, 'Fix database bug');
        $this->createRun(100, 'Authentication flow');

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([
            'AiRunSearch' => ['q' => 'authentication'],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(2);
    }

    public function testSearchReturnsAllWhenNoFilters(): void
    {
        $this->createRun(100, 'Run 1');
        $this->createRun(100, 'Run 2');
        $this->createRun(100, 'Run 3');

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([], 100);

        verify($dataProvider->getTotalCount())->equals(3);
    }

    public function testSearchFiltersOnSpecificProjectId(): void
    {
        $this->createRun(100, 'Run in project 1', AiRunStatus::PENDING, 1);
        $this->createRun(100, 'Run in project 4', AiRunStatus::PENDING, 4);
        $this->createRun(100, 'Another in project 1', AiRunStatus::PENDING, 1);

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([
            'AiRunSearch' => ['project_id' => 1],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(2);
        foreach ($dataProvider->getModels() as $model) {
            verify($model->project_id)->equals(1);
        }
    }

    public function testSearchWithAllProjectsIdShowsAllUserRuns(): void
    {
        $this->createRun(100, 'Run in project 1', AiRunStatus::PENDING, 1);
        $this->createRun(100, 'Run in project 4', AiRunStatus::PENDING, 4);

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([
            'AiRunSearch' => ['project_id' => ProjectContext::ALL_PROJECTS_ID],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(2);
        $projectIds = array_unique(array_map(fn($m) => $m->project_id, $dataProvider->getModels()));
        verify(count($projectIds))->greaterThan(1);
    }

    public function testSearchDefaultsToContextProjectWhenNoProjectId(): void
    {
        $this->createRun(100, 'Run in project 1', AiRunStatus::PENDING, 1);
        $this->createRun(100, 'Run in project 4', AiRunStatus::PENDING, 4);

        $searchModel = new AiRunSearch();
        $searchModel->search([], 100, 1, false);

        verify($searchModel->project_id)->equals(1);
    }

    public function testSearchDefaultsToAllProjectsWhenIsAllProjectsTrue(): void
    {
        $this->createRun(100, 'Run in project 1', AiRunStatus::PENDING, 1);
        $this->createRun(100, 'Run in project 4', AiRunStatus::PENDING, 4);

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([], 100, null, true);

        verify($searchModel->project_id)->equals(ProjectContext::ALL_PROJECTS_ID);
        verify($dataProvider->getTotalCount())->equals(2);
    }

    public function testSearchHandlesNonNumericProjectIdGracefully(): void
    {
        $this->createRun(100, 'Test run', AiRunStatus::PENDING, 1);
        $this->createRun(100, 'Another run', AiRunStatus::PENDING, 4);

        $searchModel = new AiRunSearch();
        $dataProvider = $searchModel->search([
            'AiRunSearch' => ['project_id' => 'abc'],
        ], 100, 1, false);

        verify($dataProvider)->instanceOf(\yii\data\ActiveDataProvider::class);
        // Validation fails, so fallback to context project
        verify($searchModel->project_id)->equals(1);
    }

    private function createRun(
        int $userId,
        string $summary,
        AiRunStatus $status = AiRunStatus::PENDING,
        int $projectId = 1
    ): AiRun {
        $run = new AiRun();
        $run->user_id = $userId;
        $run->project_id = $projectId;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = $summary;
        $run->status = $status->value;
        $run->save(false);

        return $run;
    }
}
