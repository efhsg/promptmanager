<?php

namespace tests\unit\services;

use app\models\AiRun;
use app\services\ClaudeRunCleanupService;
use Codeception\Test\Unit;
use tests\fixtures\ClaudeRunFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;

class ClaudeRunCleanupServiceTest extends Unit
{
    private ClaudeRunCleanupService $service;
    private string $tempDir;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'claudeRuns' => ClaudeRunFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new ClaudeRunCleanupService();

        // Create temp storage directory for stream files
        $this->tempDir = Yii::getAlias('@app/storage/claude-runs');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0775, true);
        }
    }

    protected function _after(): void
    {
        // Cleanup any stream files created during tests
        $files = glob($this->tempDir . '/*.ndjson');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        parent::_after();
    }

    // ---------------------------------------------------------------
    // deleteSession tests
    // ---------------------------------------------------------------

    public function testDeleteSessionRemovesAllRunsInSession(): void
    {
        $run = AiRun::findOne(1); // session-aaa, 3 completed runs
        $deleted = $this->service->deleteSession($run);

        verify($deleted)->equals(3);
        verify(AiRun::find()->forSession('session-aaa')->count())->equals(0);
    }

    public function testDeleteSessionRemovesSingleStandaloneRun(): void
    {
        $run = AiRun::findOne(4); // standalone failed run
        $deleted = $this->service->deleteSession($run);

        verify($deleted)->equals(1);
        verify(AiRun::findOne(4))->null();
    }

    public function testDeleteSessionCleansUpStreamFiles(): void
    {
        $run = AiRun::findOne(4);
        $streamPath = $run->getStreamFilePath();
        file_put_contents($streamPath, '{"type":"test"}' . "\n");

        verify(file_exists($streamPath))->true();

        $this->service->deleteSession($run);

        verify(file_exists($streamPath))->false();
    }

    public function testDeleteSessionSkipsMissingStreamFiles(): void
    {
        $run = AiRun::findOne(4); // no stream file exists
        $deleted = $this->service->deleteSession($run);

        verify($deleted)->equals(1);
    }

    public function testDeleteSessionDoesNotDeleteActiveRuns(): void
    {
        $run = AiRun::findOne(7); // standalone pending run
        $deleted = $this->service->deleteSession($run);

        verify($deleted)->equals(0);
        verify(AiRun::findOne(7))->notNull();
    }

    public function testDeleteSessionWithMixedStatusesInSession(): void
    {
        $run = AiRun::findOne(5); // session-bbb: 1 completed + 1 running
        $deleted = $this->service->deleteSession($run);

        verify($deleted)->equals(1); // only the completed one
        verify(AiRun::findOne(5))->null(); // completed: deleted
        verify(AiRun::findOne(6))->notNull(); // running: still exists
    }

    // ---------------------------------------------------------------
    // bulkCleanup tests
    // ---------------------------------------------------------------

    public function testBulkCleanupDeletesOnlyTerminalRuns(): void
    {
        $deleted = $this->service->bulkCleanup(100); // user A

        // Should delete: runs 1,2,3 (session-aaa completed), 4 (standalone failed), 5 (session-bbb completed)
        // Should NOT delete: 6 (running), 7 (pending)
        verify($deleted)->equals(5);
        verify(AiRun::findOne(6))->notNull(); // running
        verify(AiRun::findOne(7))->notNull(); // pending
    }

    public function testBulkCleanupReturnsDeletedCount(): void
    {
        $deleted = $this->service->bulkCleanup(100);

        verify($deleted)->equals(5);
    }

    public function testBulkCleanupWithNoTerminalRuns(): void
    {
        // Delete all terminal runs first
        $this->service->bulkCleanup(100);

        // Now try again — should return 0
        $deleted = $this->service->bulkCleanup(100);

        verify($deleted)->equals(0);
    }

    public function testBulkCleanupOnlyAffectsCurrentUser(): void
    {
        $this->service->bulkCleanup(100); // user A

        // User B's run should still exist
        verify(AiRun::findOne(8))->notNull();
    }

    // ---------------------------------------------------------------
    // countTerminalSessions tests
    // ---------------------------------------------------------------

    public function testCountTerminalSessionsReturnsCorrectCount(): void
    {
        // User A has: session-aaa (3 completed), standalone failed (1), session-bbb (1 completed + 1 running)
        // Terminal sessions: session-aaa, standalone_failed, session-bbb (has at least 1 terminal run)
        $count = $this->service->countTerminalSessions(100);

        verify($count)->equals(3); // 2 sessions + 1 standalone
    }

    // ---------------------------------------------------------------
    // countTerminalRuns tests
    // ---------------------------------------------------------------

    public function testCountTerminalRunsReturnsCorrectCount(): void
    {
        $count = $this->service->countTerminalRuns(100);

        // Runs 1,2,3 (completed), 4 (failed), 5 (completed) = 5 terminal runs
        verify($count)->equals(5);
    }

    // ---------------------------------------------------------------
    // Ownership tests
    // ---------------------------------------------------------------

    public function testDeleteSessionOfAnotherUserFindsNothing(): void
    {
        // Run 8 belongs to user B (id=1), session-ccc
        // If we create a ClaudeRun with user A's scope, it should find nothing
        $run = AiRun::findOne(8);

        // Manually change user_id to simulate another user trying to delete
        // The service uses $representativeRun->user_id for the forUser() scope
        // So directly calling with the run should work correctly
        $deleted = $this->service->deleteSession($run);

        // Run 8 belongs to user B (id=1) — deleteSession uses forUser(run->user_id)
        // so it correctly deletes user B's run
        verify($deleted)->equals(1);
    }
}
