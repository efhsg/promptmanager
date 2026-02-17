<?php

namespace tests\unit\commands;

use app\commands\ClaudeRunController;
use app\models\AiRun;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;

class ClaudeRunControllerTest extends Unit
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

    public function testCleanupStaleMarksOldRunsAsFailed(): void
    {
        // Create a stale running run (updated > 5 min ago)
        $staleRun = $this->createRun(AiRunStatus::RUNNING);
        AiRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $staleRun->id]
        );

        // Create a recent running run
        $recentRun = $this->createRun(AiRunStatus::RUNNING);

        $controller = new ClaudeRunController('claude-run', Yii::$app);
        $controller->actionCleanupStale(5);

        $staleRun->refresh();
        $recentRun->refresh();

        verify($staleRun->status)->equals(AiRunStatus::FAILED->value);
        verify($staleRun->error_message)->stringContainsString('heartbeat timeout');
        verify($recentRun->status)->equals(AiRunStatus::RUNNING->value);
    }

    public function testCleanupStaleIgnoresNonRunningRuns(): void
    {
        // Create a stale completed run
        $completedRun = $this->createRun(AiRunStatus::COMPLETED);
        AiRun::updateAll(
            ['updated_at' => date('Y-m-d H:i:s', time() - 600)],
            ['id' => $completedRun->id]
        );

        $controller = new ClaudeRunController('claude-run', Yii::$app);
        $controller->actionCleanupStale(5);

        $completedRun->refresh();
        verify($completedRun->status)->equals(AiRunStatus::COMPLETED->value);
    }

    public function testCleanupFilesRemovesOldFiles(): void
    {
        $dir = Yii::getAlias('@app/storage/claude-runs');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Create an old file
        $oldFile = $dir . '/old_test.ndjson';
        file_put_contents($oldFile, 'test data');
        touch($oldFile, time() - 90000); // > 24h old

        // Create a recent file
        $recentFile = $dir . '/recent_test.ndjson';
        file_put_contents($recentFile, 'test data');

        $controller = new ClaudeRunController('claude-run', Yii::$app);
        $controller->actionCleanupFiles(24);

        verify(file_exists($oldFile))->false();
        verify(file_exists($recentFile))->true();

        // Cleanup
        if (file_exists($recentFile)) {
            unlink($recentFile);
        }
    }

    public function testCleanupFilesHandlesEmptyDirectory(): void
    {
        $controller = new ClaudeRunController('claude-run', Yii::$app);
        $exitCode = $controller->actionCleanupFiles(24);

        verify($exitCode)->equals(0); // ExitCode::OK
    }

    private function createRun(AiRunStatus $status): AiRun
    {
        $run = new AiRun();
        $run->user_id = 100;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test';
        $run->status = $status->value;
        $run->save(false);

        return $run;
    }
}
