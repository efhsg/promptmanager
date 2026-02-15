<?php

namespace tests\unit\models;

use app\models\ClaudeRun;
use common\enums\ClaudeRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ClaudeRunTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    public function testIsActiveReturnsTrueForPending(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::PENDING->value;

        verify($run->isActive())->true();
    }

    public function testIsActiveReturnsTrueForRunning(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::RUNNING->value;

        verify($run->isActive())->true();
    }

    public function testIsActiveReturnsFalseForCompleted(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::COMPLETED->value;

        verify($run->isActive())->false();
    }

    public function testIsActiveReturnsFalseForFailed(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::FAILED->value;

        verify($run->isActive())->false();
    }

    public function testIsActiveReturnsFalseForCancelled(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::CANCELLED->value;

        verify($run->isActive())->false();
    }

    public function testIsTerminalReturnsTrueForCompleted(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::COMPLETED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsTrueForFailed(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::FAILED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsTrueForCancelled(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::CANCELLED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsFalseForPending(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::PENDING->value;

        verify($run->isTerminal())->false();
    }

    public function testIsTerminalReturnsFalseForRunning(): void
    {
        $run = new ClaudeRun();
        $run->status = ClaudeRunStatus::RUNNING->value;

        verify($run->isTerminal())->false();
    }

    public function testMarkRunning(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(12345);

        verify($run->status)->equals(ClaudeRunStatus::RUNNING->value);
        verify($run->pid)->equals(12345);
        verify($run->started_at)->notNull();
    }

    public function testMarkCompleted(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(123);

        $metadata = ['duration_ms' => 5000, 'model' => 'opus'];
        $run->markCompleted('Result text here', $metadata, 'stream log data');

        verify($run->status)->equals(ClaudeRunStatus::COMPLETED->value);
        verify($run->result_text)->equals('Result text here');
        verify($run->pid)->null();
        verify($run->completed_at)->notNull();
        verify($run->stream_log)->equals('stream log data');

        $decoded = json_decode($run->result_metadata, true);
        verify($decoded['duration_ms'])->equals(5000);
        verify($decoded['model'])->equals('opus');
    }

    public function testMarkFailed(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(123);
        $run->markFailed('Something went wrong', 'partial log');

        verify($run->status)->equals(ClaudeRunStatus::FAILED->value);
        verify($run->error_message)->equals('Something went wrong');
        verify($run->pid)->null();
        verify($run->completed_at)->notNull();
        verify($run->stream_log)->equals('partial log');
    }

    public function testMarkCancelled(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(123);
        $run->markCancelled('partial log data');

        verify($run->status)->equals(ClaudeRunStatus::CANCELLED->value);
        verify($run->pid)->null();
        verify($run->completed_at)->notNull();
        verify($run->stream_log)->equals('partial log data');
    }

    public function testGetStreamFilePath(): void
    {
        $run = new ClaudeRun();
        $run->id = 42;

        $path = $run->getStreamFilePath();

        verify($path)->stringContainsString('storage/claude-runs/42.ndjson');
    }

    public function testDefaultStatusIsPending(): void
    {
        $run = new ClaudeRun();

        verify($run->status)->equals(ClaudeRunStatus::PENDING->value);
    }

    public function testGetDecodedOptionsReturnsEmptyArrayWhenNull(): void
    {
        $run = new ClaudeRun();
        $run->options = null;

        verify($run->getDecodedOptions())->equals([]);
    }

    public function testGetDecodedOptionsReturnsArray(): void
    {
        $run = new ClaudeRun();
        $run->options = json_encode(['model' => 'opus', 'permissionMode' => 'plan']);

        $decoded = $run->getDecodedOptions();
        verify($decoded['model'])->equals('opus');
        verify($decoded['permissionMode'])->equals('plan');
    }

    public function testGetDecodedResultMetadataReturnsEmptyArrayWhenNull(): void
    {
        $run = new ClaudeRun();
        $run->result_metadata = null;

        verify($run->getDecodedResultMetadata())->equals([]);
    }

    public function testValidationRequiresUserIdProjectIdAndPrompt(): void
    {
        $run = new ClaudeRun();
        $run->user_id = null;
        $run->project_id = null;
        $run->prompt_markdown = null;

        verify($run->validate())->false();
        verify(array_key_exists('user_id', $run->errors))->true();
        verify(array_key_exists('project_id', $run->errors))->true();
        verify(array_key_exists('prompt_markdown', $run->errors))->true();
    }

    public function testHeartbeatUpdatesTimestamp(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(123);
        $oldTimestamp = $run->updated_at;

        // Override timestamp to simulate time passing
        ClaudeRun::setTimestampOverride('2099-01-01 00:00:00');
        $run->heartbeat();
        ClaudeRun::setTimestampOverride(null);

        verify($run->updated_at)->notEquals($oldTimestamp);
    }

    public function testSetSessionIdFromResultSetsWhenNull(): void
    {
        $run = $this->createSavedRun();
        verify($run->session_id)->null();

        $run->setSessionIdFromResult('test-session-123');
        $run->refresh();

        verify($run->session_id)->equals('test-session-123');
    }

    public function testSetSessionIdFromResultDoesNotOverwrite(): void
    {
        $run = $this->createSavedRun();
        $run->session_id = 'existing-session';
        $run->save(false);

        $run->setSessionIdFromResult('new-session');
        $run->refresh();

        verify($run->session_id)->equals('existing-session');
    }

    // ---------------------------------------------------------------
    // getDisplaySummary tests
    // ---------------------------------------------------------------

    public function testGetDisplaySummaryPrefersSessionSummaryLatestAttribute(): void
    {
        $run = new ClaudeRun();
        $run->prompt_summary = 'Fix the bug';
        $run->session_summary = 'Fixed login CSRF';
        $run->session_summary_latest = 'Resolved auth issues across 3 runs';

        verify($run->getDisplaySummary())->equals('Resolved auth issues across 3 runs');
    }

    public function testGetDisplaySummaryFallsBackToOwnSessionSummary(): void
    {
        $run = new ClaudeRun();
        $run->prompt_summary = 'Fix the bug';
        $run->session_summary = 'Fixed login CSRF validation';

        verify($run->getDisplaySummary())->equals('Fixed login CSRF validation');
    }

    public function testGetDisplaySummaryFallsBackToPromptSummary(): void
    {
        $run = new ClaudeRun();
        $run->prompt_summary = 'Fix the login bug in the auth module';

        verify($run->getDisplaySummary())->equals('Fix the login bug in the auth module');
    }

    public function testGetDisplaySummaryReturnsDashWhenEmpty(): void
    {
        $run = new ClaudeRun();

        verify($run->getDisplaySummary())->equals('-');
    }

    // ---------------------------------------------------------------
    // claimForProcessing tests
    // ---------------------------------------------------------------

    public function testClaimForProcessingSucceeds(): void
    {
        $run = $this->createSavedRun();
        verify($run->status)->equals(ClaudeRunStatus::PENDING->value);

        $result = $run->claimForProcessing(12345);

        verify($result)->true();
        verify($run->status)->equals(ClaudeRunStatus::RUNNING->value);
        verify($run->pid)->equals(12345);
        verify($run->started_at)->notNull();
    }

    public function testClaimForProcessingFailsWhenAlreadyClaimed(): void
    {
        $run = $this->createSavedRun();

        // First claim succeeds
        $result1 = $run->claimForProcessing(111);
        verify($result1)->true();

        // Second claim on the same run fails (status is no longer PENDING)
        $run2 = ClaudeRun::findOne($run->id);
        $result2 = $run2->claimForProcessing(222);
        verify($result2)->false();

        // Original PID is preserved
        $run->refresh();
        verify($run->pid)->equals(111);
    }

    public function testClaimForProcessingFailsWhenNotPending(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(999);

        $result = $run->claimForProcessing(123);

        verify($result)->false();
        // PID unchanged
        $run->refresh();
        verify($run->pid)->equals(999);
    }

    private function createSavedRun(): ClaudeRun
    {
        $run = new ClaudeRun();
        $run->user_id = 100;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test prompt';
        $run->save(false);

        return $run;
    }
}
