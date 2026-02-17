<?php

namespace tests\unit\models;

use app\models\AiRun;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class AiRunQueryTest extends Unit
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
        // Clean up any existing runs
        AiRun::deleteAll([]);
    }

    public function testActiveFiltersOnPendingAndRunning(): void
    {
        $this->createRun(AiRunStatus::PENDING);
        $this->createRun(AiRunStatus::RUNNING);
        $this->createRun(AiRunStatus::COMPLETED);
        $this->createRun(AiRunStatus::FAILED);
        $this->createRun(AiRunStatus::CANCELLED);

        $activeRuns = AiRun::find()->active()->all();

        verify(count($activeRuns))->equals(2);
        foreach ($activeRuns as $run) {
            verify(in_array($run->status, AiRunStatus::activeValues(), true))->true();
        }
    }

    public function testTerminalFiltersOnCompletedFailedCancelled(): void
    {
        $this->createRun(AiRunStatus::PENDING);
        $this->createRun(AiRunStatus::RUNNING);
        $this->createRun(AiRunStatus::COMPLETED);
        $this->createRun(AiRunStatus::FAILED);
        $this->createRun(AiRunStatus::CANCELLED);

        $terminalRuns = AiRun::find()->terminal()->all();

        verify(count($terminalRuns))->equals(3);
        foreach ($terminalRuns as $run) {
            verify(in_array($run->status, AiRunStatus::terminalValues(), true))->true();
        }
    }

    public function testForUserFiltersOnUserId(): void
    {
        $this->createRun(AiRunStatus::PENDING, 100);
        $this->createRun(AiRunStatus::PENDING, 100);
        $this->createRun(AiRunStatus::PENDING, 1);

        $runs = AiRun::find()->forUser(100)->all();
        verify(count($runs))->equals(2);

        $runs = AiRun::find()->forUser(1)->all();
        verify(count($runs))->equals(1);

        $runs = AiRun::find()->forUser(999)->all();
        verify(count($runs))->equals(0);
    }

    public function testForProjectFiltersOnProjectId(): void
    {
        $this->createRun(AiRunStatus::PENDING, 100, 1);
        $this->createRun(AiRunStatus::PENDING, 100, 1);
        $this->createRun(AiRunStatus::PENDING, 100, 2);

        $runs = AiRun::find()->forProject(1)->all();
        verify(count($runs))->equals(2);

        $runs = AiRun::find()->forProject(2)->all();
        verify(count($runs))->equals(1);
    }

    public function testForSessionFiltersOnSessionId(): void
    {
        $run1 = $this->createRun(AiRunStatus::COMPLETED);
        $run1->session_id = 'session-abc';
        $run1->save(false);

        $run2 = $this->createRun(AiRunStatus::COMPLETED);
        $run2->session_id = 'session-abc';
        $run2->save(false);

        $run3 = $this->createRun(AiRunStatus::COMPLETED);
        $run3->session_id = 'session-xyz';
        $run3->save(false);

        $runs = AiRun::find()->forSession('session-abc')->all();
        verify(count($runs))->equals(2);
    }

    public function testWithStatusFiltersOnSpecificStatus(): void
    {
        $this->createRun(AiRunStatus::PENDING);
        $this->createRun(AiRunStatus::RUNNING);
        $this->createRun(AiRunStatus::COMPLETED);

        $runs = AiRun::find()->withStatus(AiRunStatus::RUNNING)->all();
        verify(count($runs))->equals(1);
        verify($runs[0]->status)->equals(AiRunStatus::RUNNING->value);
    }

    public function testCreatedBeforeFiltersOnTimestamp(): void
    {
        $run1 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 10:00:00'],
            ['id' => $run1->id]
        );

        $run2 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 11:00:00'],
            ['id' => $run2->id]
        );

        $run3 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 12:00:00'],
            ['id' => $run3->id]
        );

        $runs = AiRun::find()->createdBefore('2026-01-01 11:00:00')->all();
        verify(count($runs))->equals(1);
        verify($runs[0]->id)->equals($run1->id);
    }

    public function testOrderedByCreatedAscSortsChronologically(): void
    {
        $run1 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 12:00:00'],
            ['id' => $run1->id]
        );

        $run2 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 10:00:00'],
            ['id' => $run2->id]
        );

        $run3 = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['created_at' => '2026-01-01 11:00:00'],
            ['id' => $run3->id]
        );

        $runs = AiRun::find()->orderedByCreatedAsc()->all();
        verify(count($runs))->equals(3);
        verify($runs[0]->id)->equals($run2->id);
        verify($runs[1]->id)->equals($run3->id);
        verify($runs[2]->id)->equals($run1->id);
    }

    public function testStaleFindsOldRunningRuns(): void
    {
        // Create a "stale" run with old updated_at
        $staleRun = $this->createRun(AiRunStatus::RUNNING);
        AiRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $staleRun->id]
        );

        // Create a recent running run
        $this->createRun(AiRunStatus::RUNNING);

        // Create a stale but completed run (should not be found)
        $completedRun = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $completedRun->id]
        );

        $staleRuns = AiRun::find()->stale(5)->all();
        verify(count($staleRuns))->equals(1);
        verify($staleRuns[0]->id)->equals($staleRun->id);
    }

    private function createRun(AiRunStatus $status, int $userId = 100, int $projectId = 1): AiRun
    {
        $run = new AiRun();
        $run->user_id = $userId;
        $run->project_id = $projectId;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test';
        $run->status = $status->value;
        $run->save(false);

        return $run;
    }
}
