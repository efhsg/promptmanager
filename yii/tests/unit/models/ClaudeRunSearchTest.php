<?php

namespace tests\unit\models;

use app\models\ClaudeRun;
use app\models\ClaudeRunSearch;
use common\enums\ClaudeRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ClaudeRunSearchTest extends Unit
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
        ClaudeRun::deleteAll([]);
    }

    public function testSearchReturnsOnlyRunsForUser(): void
    {
        $this->createRun(100, 'My prompt');
        $this->createRun(100, 'Another prompt');
        $this->createRun(1, 'Other user prompt');

        $searchModel = new ClaudeRunSearch();
        $dataProvider = $searchModel->search([], 100);

        verify($dataProvider->getTotalCount())->equals(2);
    }

    public function testSearchFiltersOnStatus(): void
    {
        $this->createRun(100, 'Pending run', ClaudeRunStatus::PENDING);
        $this->createRun(100, 'Completed run', ClaudeRunStatus::COMPLETED);
        $this->createRun(100, 'Failed run', ClaudeRunStatus::FAILED);

        $searchModel = new ClaudeRunSearch();
        $dataProvider = $searchModel->search([
            'ClaudeRunSearch' => ['status' => 'completed'],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(1);
    }

    public function testSearchFiltersOnPromptSummary(): void
    {
        $this->createRun(100, 'Refactor authentication');
        $this->createRun(100, 'Fix database bug');
        $this->createRun(100, 'Authentication flow');

        $searchModel = new ClaudeRunSearch();
        $dataProvider = $searchModel->search([
            'ClaudeRunSearch' => ['q' => 'authentication'],
        ], 100);

        verify($dataProvider->getTotalCount())->equals(2);
    }

    public function testSearchReturnsAllWhenNoFilters(): void
    {
        $this->createRun(100, 'Run 1');
        $this->createRun(100, 'Run 2');
        $this->createRun(100, 'Run 3');

        $searchModel = new ClaudeRunSearch();
        $dataProvider = $searchModel->search([], 100);

        verify($dataProvider->getTotalCount())->equals(3);
    }

    private function createRun(
        int $userId,
        string $summary,
        ClaudeRunStatus $status = ClaudeRunStatus::PENDING
    ): ClaudeRun {
        $run = new ClaudeRun();
        $run->user_id = $userId;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = $summary;
        $run->status = $status->value;
        $run->save(false);

        return $run;
    }
}
