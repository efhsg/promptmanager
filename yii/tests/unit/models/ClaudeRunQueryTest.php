<?php

namespace tests\unit\models;

use app\models\ClaudeRun;
use common\enums\ClaudeRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ClaudeRunQueryTest extends Unit
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
        ClaudeRun::deleteAll([]);
    }

    public function testActiveFiltersOnPendingAndRunning(): void
    {
        $this->createRun(ClaudeRunStatus::PENDING);
        $this->createRun(ClaudeRunStatus::RUNNING);
        $this->createRun(ClaudeRunStatus::COMPLETED);
        $this->createRun(ClaudeRunStatus::FAILED);
        $this->createRun(ClaudeRunStatus::CANCELLED);

        $activeRuns = ClaudeRun::find()->active()->all();

        verify(count($activeRuns))->equals(2);
        foreach ($activeRuns as $run) {
            verify(in_array($run->status, ClaudeRunStatus::activeValues(), true))->true();
        }
    }

    public function testTerminalFiltersOnCompletedFailedCancelled(): void
    {
        $this->createRun(ClaudeRunStatus::PENDING);
        $this->createRun(ClaudeRunStatus::RUNNING);
        $this->createRun(ClaudeRunStatus::COMPLETED);
        $this->createRun(ClaudeRunStatus::FAILED);
        $this->createRun(ClaudeRunStatus::CANCELLED);

        $terminalRuns = ClaudeRun::find()->terminal()->all();

        verify(count($terminalRuns))->equals(3);
        foreach ($terminalRuns as $run) {
            verify(in_array($run->status, ClaudeRunStatus::terminalValues(), true))->true();
        }
    }

    public function testForUserFiltersOnUserId(): void
    {
        $this->createRun(ClaudeRunStatus::PENDING, 100);
        $this->createRun(ClaudeRunStatus::PENDING, 100);
        $this->createRun(ClaudeRunStatus::PENDING, 1);

        $runs = ClaudeRun::find()->forUser(100)->all();
        verify(count($runs))->equals(2);

        $runs = ClaudeRun::find()->forUser(1)->all();
        verify(count($runs))->equals(1);

        $runs = ClaudeRun::find()->forUser(999)->all();
        verify(count($runs))->equals(0);
    }

    public function testForProjectFiltersOnProjectId(): void
    {
        $this->createRun(ClaudeRunStatus::PENDING, 100, 1);
        $this->createRun(ClaudeRunStatus::PENDING, 100, 1);
        $this->createRun(ClaudeRunStatus::PENDING, 100, 2);

        $runs = ClaudeRun::find()->forProject(1)->all();
        verify(count($runs))->equals(2);

        $runs = ClaudeRun::find()->forProject(2)->all();
        verify(count($runs))->equals(1);
    }

    public function testForSessionFiltersOnSessionId(): void
    {
        $run1 = $this->createRun(ClaudeRunStatus::COMPLETED);
        $run1->session_id = 'session-abc';
        $run1->save(false);

        $run2 = $this->createRun(ClaudeRunStatus::COMPLETED);
        $run2->session_id = 'session-abc';
        $run2->save(false);

        $run3 = $this->createRun(ClaudeRunStatus::COMPLETED);
        $run3->session_id = 'session-xyz';
        $run3->save(false);

        $runs = ClaudeRun::find()->forSession('session-abc')->all();
        verify(count($runs))->equals(2);
    }

    public function testWithStatusFiltersOnSpecificStatus(): void
    {
        $this->createRun(ClaudeRunStatus::PENDING);
        $this->createRun(ClaudeRunStatus::RUNNING);
        $this->createRun(ClaudeRunStatus::COMPLETED);

        $runs = ClaudeRun::find()->withStatus(ClaudeRunStatus::RUNNING)->all();
        verify(count($runs))->equals(1);
        verify($runs[0]->status)->equals(ClaudeRunStatus::RUNNING->value);
    }

    public function testCreatedBeforeFiltersOnTimestamp(): void
    {
        $run1 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 10:00:00'],
            ['id' => $run1->id]
        );

        $run2 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 11:00:00'],
            ['id' => $run2->id]
        );

        $run3 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 12:00:00'],
            ['id' => $run3->id]
        );

        $runs = ClaudeRun::find()->createdBefore('2026-01-01 11:00:00')->all();
        verify(count($runs))->equals(1);
        verify($runs[0]->id)->equals($run1->id);
    }

    public function testOrderedByCreatedAscSortsChronologically(): void
    {
        $run1 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 12:00:00'],
            ['id' => $run1->id]
        );

        $run2 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 10:00:00'],
            ['id' => $run2->id]
        );

        $run3 = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['created_at' => '2026-01-01 11:00:00'],
            ['id' => $run3->id]
        );

        $runs = ClaudeRun::find()->orderedByCreatedAsc()->all();
        verify(count($runs))->equals(3);
        verify($runs[0]->id)->equals($run2->id);
        verify($runs[1]->id)->equals($run3->id);
        verify($runs[2]->id)->equals($run1->id);
    }

    public function testStaleFindsOldRunningRuns(): void
    {
        // Create a "stale" run with old updated_at
        $staleRun = $this->createRun(ClaudeRunStatus::RUNNING);
        ClaudeRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $staleRun->id]
        );

        // Create a recent running run
        $this->createRun(ClaudeRunStatus::RUNNING);

        // Create a stale but completed run (should not be found)
        $completedRun = $this->createRun(ClaudeRunStatus::COMPLETED);
        ClaudeRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $completedRun->id]
        );

        $staleRuns = ClaudeRun::find()->stale(5)->all();
        verify(count($staleRuns))->equals(1);
        verify($staleRuns[0]->id)->equals($staleRun->id);
    }

    private function createRun(ClaudeRunStatus $status, int $userId = 100, int $projectId = 1): ClaudeRun
    {
        $run = new ClaudeRun();
        $run->user_id = $userId;
        $run->project_id = $projectId;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test';
        $run->status = $status->value;
        $run->save(false);

        return $run;
    }
}
