<?php

namespace tests\unit\jobs;

use app\handlers\ClaudeQuickHandler;
use app\jobs\RunClaudeJob;
use app\models\AiRun;
use app\services\ClaudeCliService;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class RunClaudeJobTest extends Unit
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

    public function testSkipsWhenRunNotFound(): void
    {
        $job = new RunClaudeJob();
        $job->runId = 99999;

        // Should not throw — just silently skip
        $job->execute(null);

        $this->assertTrue(true);
    }

    public function testSkipsWhenRunIsNotPending(): void
    {
        $run = $this->createRun(AiRunStatus::RUNNING);

        $job = new RunClaudeJob();
        $job->runId = $run->id;

        $job->execute(null);

        // Status should remain unchanged
        $run->refresh();
        verify($run->status)->equals(AiRunStatus::RUNNING->value);
    }

    public function testCanRetryReturnsFalse(): void
    {
        $job = new RunClaudeJob();

        verify($job->canRetry(1, new \Exception('test')))->false();
        verify($job->canRetry(5, new \Exception('test')))->false();
    }

    public function testGetTtrReturns3900(): void
    {
        $job = new RunClaudeJob();

        verify($job->getTtr())->equals(3900);
    }

    public function testExecuteCompletesSuccessfully(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockCliService = $this->createMock(\app\services\ClaudeCliService::class);
        $mockCliService->method('executeStreaming')
            ->willReturnCallback(function ($prompt, $dir, $onLine) {
                $onLine('{"type":"assistant","message":"hello"}');
                $onLine('{"type":"result","result":"Final answer","session_id":"ses-1"}');
                return ['exitCode' => 0, 'error' => ''];
            });

        $job = new class extends RunClaudeJob {
            public \app\services\ClaudeCliService $mockService;

            protected function createCliService(): \app\services\ClaudeCliService
            {
                return $this->mockService;
            }
        };
        $job->runId = $run->id;
        $job->mockService = $mockCliService;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->result_text)->stringContainsString('Final answer');
        verify($run->pid)->null();
        verify($run->completed_at)->notNull();
    }

    public function testExecuteMarksFailedOnNonZeroExitCode(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockCliService = $this->createMock(\app\services\ClaudeCliService::class);
        $mockCliService->method('executeStreaming')
            ->willReturn(['exitCode' => 1, 'error' => 'CLI timeout']);

        $job = new class extends RunClaudeJob {
            public \app\services\ClaudeCliService $mockService;

            protected function createCliService(): \app\services\ClaudeCliService
            {
                return $this->mockService;
            }
        };
        $job->runId = $run->id;
        $job->mockService = $mockCliService;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);
        verify($run->error_message)->equals('CLI timeout');
    }

    public function testGeneratesSessionSummaryOnCompletion(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockCliService = $this->createMock(ClaudeCliService::class);
        $mockCliService->method('executeStreaming')
            ->willReturnCallback(function ($prompt, $dir, $onLine) {
                $onLine('{"type":"result","result":"Refactored the auth module to use JWT tokens"}');
                return ['exitCode' => 0, 'error' => ''];
            });

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->expects($this->once())
            ->method('run')
            ->with('session-summary', $this->anything())
            ->willReturn(['success' => true, 'output' => 'Refactored auth to JWT']);

        $job = new class extends RunClaudeJob {
            public ClaudeCliService $mockService;
            public ClaudeQuickHandler $mockHandler;

            protected function createCliService(): ClaudeCliService
            {
                return $this->mockService;
            }

            protected function createQuickHandler(): ClaudeQuickHandler
            {
                return $this->mockHandler;
            }
        };
        $job->runId = $run->id;
        $job->mockService = $mockCliService;
        $job->mockHandler = $mockHandler;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->session_summary)->equals('Refactored auth to JWT');
    }

    public function testSessionSummaryFailureDoesNotAffectRunStatus(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockCliService = $this->createMock(ClaudeCliService::class);
        $mockCliService->method('executeStreaming')
            ->willReturnCallback(function ($prompt, $dir, $onLine) {
                $onLine('{"type":"result","result":"Implemented feature X with full test coverage"}');
                return ['exitCode' => 0, 'error' => ''];
            });

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')
            ->willThrowException(new \RuntimeException('AI service unavailable'));

        $job = new class extends RunClaudeJob {
            public ClaudeCliService $mockService;
            public ClaudeQuickHandler $mockHandler;

            protected function createCliService(): ClaudeCliService
            {
                return $this->mockService;
            }

            protected function createQuickHandler(): ClaudeQuickHandler
            {
                return $this->mockHandler;
            }
        };
        $job->runId = $run->id;
        $job->mockService = $mockCliService;
        $job->mockHandler = $mockHandler;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->session_summary)->null();
    }

    private function createRun(AiRunStatus $status): AiRun
    {
        $run = new AiRun();
        $run->user_id = 100;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test';
        $run->status = $status->value;
        $run->working_directory = '/tmp';
        $run->save(false);

        return $run;
    }
}
