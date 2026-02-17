<?php

namespace tests\unit\models;

use app\models\AiRun;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class AiRunTest extends Unit
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
        $run = new AiRun();
        $run->status = AiRunStatus::PENDING->value;

        verify($run->isActive())->true();
    }

    public function testIsActiveReturnsTrueForRunning(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::RUNNING->value;

        verify($run->isActive())->true();
    }

    public function testIsActiveReturnsFalseForCompleted(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::COMPLETED->value;

        verify($run->isActive())->false();
    }

    public function testIsActiveReturnsFalseForFailed(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::FAILED->value;

        verify($run->isActive())->false();
    }

    public function testIsActiveReturnsFalseForCancelled(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::CANCELLED->value;

        verify($run->isActive())->false();
    }

    public function testIsTerminalReturnsTrueForCompleted(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::COMPLETED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsTrueForFailed(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::FAILED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsTrueForCancelled(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::CANCELLED->value;

        verify($run->isTerminal())->true();
    }

    public function testIsTerminalReturnsFalseForPending(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::PENDING->value;

        verify($run->isTerminal())->false();
    }

    public function testIsTerminalReturnsFalseForRunning(): void
    {
        $run = new AiRun();
        $run->status = AiRunStatus::RUNNING->value;

        verify($run->isTerminal())->false();
    }

    public function testMarkRunning(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(12345);

        verify($run->status)->equals(AiRunStatus::RUNNING->value);
        verify($run->pid)->equals(12345);
        verify($run->started_at)->notNull();
    }

    public function testMarkCompleted(): void
    {
        $run = $this->createSavedRun();
        $run->markRunning(123);

        $metadata = ['duration_ms' => 5000, 'model' => 'opus'];
        $run->markCompleted('Result text here', $metadata, 'stream log data');

        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
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

        verify($run->status)->equals(AiRunStatus::FAILED->value);
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

        verify($run->status)->equals(AiRunStatus::CANCELLED->value);
        verify($run->pid)->null();
        verify($run->completed_at)->notNull();
        verify($run->stream_log)->equals('partial log data');
    }

    public function testGetStreamFilePath(): void
    {
        $run = new AiRun();
        $run->id = 42;

        $path = $run->getStreamFilePath();

        verify($path)->stringContainsString('storage/claude-runs/42.ndjson');
    }

    public function testDefaultStatusIsPending(): void
    {
        $run = new AiRun();

        verify($run->status)->equals(AiRunStatus::PENDING->value);
    }

    public function testGetDecodedOptionsReturnsEmptyArrayWhenNull(): void
    {
        $run = new AiRun();
        $run->options = null;

        verify($run->getDecodedOptions())->equals([]);
    }

    public function testGetDecodedOptionsReturnsArray(): void
    {
        $run = new AiRun();
        $run->options = json_encode(['model' => 'opus', 'permissionMode' => 'plan']);

        $decoded = $run->getDecodedOptions();
        verify($decoded['model'])->equals('opus');
        verify($decoded['permissionMode'])->equals('plan');
    }

    public function testGetDecodedResultMetadataReturnsEmptyArrayWhenNull(): void
    {
        $run = new AiRun();
        $run->result_metadata = null;

        verify($run->getDecodedResultMetadata())->equals([]);
    }

    public function testValidationRequiresUserIdProjectIdAndPrompt(): void
    {
        $run = new AiRun();
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
        AiRun::setTimestampOverride('2099-01-01 00:00:00');
        $run->heartbeat();
        AiRun::setTimestampOverride(null);

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
        $run = new AiRun();
        $run->prompt_summary = 'Fix the bug';
        $run->session_summary = 'Fixed login CSRF';
        $run->session_summary_latest = 'Resolved auth issues across 3 runs';

        verify($run->getDisplaySummary())->equals('Resolved auth issues across 3 runs');
    }

    public function testGetDisplaySummaryFallsBackToOwnSessionSummary(): void
    {
        $run = new AiRun();
        $run->prompt_summary = 'Fix the bug';
        $run->session_summary = 'Fixed login CSRF validation';

        verify($run->getDisplaySummary())->equals('Fixed login CSRF validation');
    }

    public function testGetDisplaySummaryFallsBackToPromptSummary(): void
    {
        $run = new AiRun();
        $run->prompt_summary = 'Fix the login bug in the auth module';

        verify($run->getDisplaySummary())->equals('Fix the login bug in the auth module');
    }

    public function testGetDisplaySummaryReturnsDashWhenEmpty(): void
    {
        $run = new AiRun();

        verify($run->getDisplaySummary())->equals('-');
    }

    // ---------------------------------------------------------------
    // claimForProcessing tests
    // ---------------------------------------------------------------

    public function testClaimForProcessingSucceeds(): void
    {
        $run = $this->createSavedRun();
        verify($run->status)->equals(AiRunStatus::PENDING->value);

        $result = $run->claimForProcessing(12345);

        verify($result)->true();
        verify($run->status)->equals(AiRunStatus::RUNNING->value);
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
        $run2 = AiRun::findOne($run->id);
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

    private function createSavedRun(): AiRun
    {
        $run = new AiRun();
        $run->user_id = 100;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test prompt';
        $run->save(false);

        return $run;
    }
}
