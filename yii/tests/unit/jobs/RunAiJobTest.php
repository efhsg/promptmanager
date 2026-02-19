<?php

namespace tests\unit\jobs;

use app\handlers\AiQuickHandler;
use app\jobs\RunAiJob;
use app\models\AiRun;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiStreamingProviderInterface;
use common\enums\AiRunStatus;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class RunAiJobTest extends Unit
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
        $job = new RunAiJob();
        $job->runId = 99999;

        // Should not throw — just silently skip
        $job->execute(null);

        $this->assertTrue(true);
    }

    public function testSkipsWhenRunIsNotPending(): void
    {
        $run = $this->createRun(AiRunStatus::RUNNING);

        $job = new RunAiJob();
        $job->runId = $run->id;

        $job->execute(null);

        // Status should remain unchanged
        $run->refresh();
        verify($run->status)->equals(AiRunStatus::RUNNING->value);
    }

    public function testCanRetryReturnsFalse(): void
    {
        $job = new RunAiJob();

        verify($job->canRetry(1, new \Exception('test')))->false();
        verify($job->canRetry(5, new \Exception('test')))->false();
    }

    public function testGetTtrReturns3900(): void
    {
        $job = new RunAiJob();

        verify($job->getTtr())->equals(3900);
    }

    public function testExecuteCompletesSuccessfully(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"assistant","message":"hello"}');
            $onLine('{"type":"result","result":"Final answer","session_id":"ses-1"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

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

        $mockProvider = $this->createStreamingProviderMock(null, ['exitCode' => 1, 'error' => 'CLI timeout']);

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);
        verify($run->error_message)->equals('CLI timeout');
    }

    public function testGeneratesSessionSummaryOnCompletion(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"result","result":"Refactored the auth module to use JWT tokens"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $mockHandler = $this->createMock(AiQuickHandler::class);
        $mockHandler->expects($this->once())
            ->method('run')
            ->with('session-summary', $this->anything())
            ->willReturn(['success' => true, 'output' => 'Refactored auth to JWT']);

        $job = $this->createJobWithProviderAndHandler($mockProvider, $mockHandler);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->session_summary)->equals('Refactored auth to JWT');
    }

    public function testSessionSummaryFailureDoesNotAffectRunStatus(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"result","result":"Implemented feature X with full test coverage"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $mockHandler = $this->createMock(AiQuickHandler::class);
        $mockHandler->method('run')
            ->willThrowException(new \RuntimeException('AI service unavailable'));

        $job = $this->createJobWithProviderAndHandler($mockProvider, $mockHandler);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->session_summary)->null();
    }

    public function testDoneMarkerWrittenBeforeTerminalStatus(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"result","result":"Done test"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);

        // Exactly one [DONE] marker must be present in the stream file
        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testDoneMarkerWrittenOnFailure(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(null, ['exitCode' => 1, 'error' => 'Process crashed']);

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);

        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testDoneMarkerWrittenOnceOnThrowableDuringExecution(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"assistant","message":"partial"}');
            throw new \Error('Unexpected type error in provider');
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);
        verify($run->error_message)->equals('Unexpected type error in provider');

        // Exactly one [DONE] marker — catch block writes it since $doneWritten is false
        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testHandlesDeletedStreamFileGracefully(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) use ($run) {
            $onLine('{"type":"assistant","message":"hello"}');
            // Simulate concurrent cleanup deleting the stream file mid-run
            @unlink($run->getStreamFilePath());
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->stream_log)->null();
        verify($run->error_message)->null();

        @unlink($run->getStreamFilePath());
    }

    public function testCancellationMidRunMarksRunAsCancelled(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);
        $runId = $run->id;

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) use ($runId) {
            $onLine('{"type":"assistant","message":"working on it"}');
            AiRun::updateAll(
                ['status' => AiRunStatus::CANCELLED->value],
                ['id' => $runId]
            );
            throw new \RuntimeException('Run cancelled by user');
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $runId;

        $job->execute(null);

        $run->refresh();
        verify($run->error_message)->equals('Run cancelled by user');

        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testSessionIdExtractedFromResultLine(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);
        verify($run->session_id)->null();

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"result","result":"Done","session_id":"ses-from-result"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->session_id)->equals('ses-from-result');

        @unlink($run->getStreamFilePath());
    }

    public function testSessionIdExtractedFromSystemLine(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);
        verify($run->session_id)->null();

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"system","session_id":"ses-from-system","message":"init"}');
            $onLine('{"type":"result","result":"Done","session_id":"ses-from-result"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        // system line is encountered first, so session_id should come from there
        verify($run->session_id)->equals('ses-from-system');

        @unlink($run->getStreamFilePath());
    }

    public function testFallbackErrorMessageWhenProviderErrorIsEmpty(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(null, ['exitCode' => 42, 'error' => '']);

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);
        verify($run->error_message)->equals('AI CLI exited with code 42');

        @unlink($run->getStreamFilePath());
    }

    public function testProviderNotFoundMarksRunAsFailed(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING, 'removed-provider');

        $job = new RunAiJob();
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::FAILED->value);
        verify($run->error_message)->equals("Provider 'removed-provider' is not configured");

        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testNonStreamingProviderUsesSyncFallback(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        // Create a mock that only implements AiProviderInterface (not AiStreamingProviderInterface)
        $mockProvider = $this->createMock(AiProviderInterface::class);
        $mockProvider->method('execute')
            ->willReturn([
                'success' => true,
                'output' => 'sync result',
                'error' => '',
                'exitCode' => 0,
            ]);

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->result_text)->equals('sync result');

        $streamFilePath = $run->getStreamFilePath();
        verify(file_exists($streamFilePath))->true();
        $content = file_get_contents($streamFilePath);
        verify($content)->stringContainsString('"type":"sync_result"');
        verify($content)->stringContainsString('"text":"sync result"');
        verify(substr_count($content, '[DONE]'))->equals(1);

        @unlink($streamFilePath);
    }

    public function testStreamingProviderUsesExecuteStreaming(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);

        $mockProvider = $this->createStreamingProviderMock(function ($prompt, $dir, $onLine) {
            $onLine('{"type":"result","result":"streaming result"}');
            return ['exitCode' => 0, 'error' => ''];
        });

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($run->result_text)->equals('streaming result');

        @unlink($run->getStreamFilePath());
    }

    private function createRun(AiRunStatus $status, string $provider = 'claude'): AiRun
    {
        $run = new AiRun();
        $run->user_id = 100;
        $run->project_id = 1;
        $run->prompt_markdown = 'Test prompt';
        $run->prompt_summary = 'Test';
        $run->status = $status->value;
        $run->working_directory = '/tmp';
        $run->provider = $provider;
        $run->save(false);

        return $run;
    }

    /**
     * Creates a mock that implements both AiProviderInterface and AiStreamingProviderInterface.
     *
     * parseStreamResult() delegates to the real ClaudeCliProvider logic by default,
     * parsing NDJSON stream logs for result text, session_id and metadata.
     */
    private function createStreamingProviderMock(?callable $callback = null, ?array $returnValue = null): AiProviderInterface
    {
        $mock = $this->createMockForIntersectionOfInterfaces([
            AiProviderInterface::class,
            AiStreamingProviderInterface::class,
        ]);
        if ($callback !== null) {
            $mock->method('executeStreaming')->willReturnCallback($callback);
        } elseif ($returnValue !== null) {
            $mock->method('executeStreaming')->willReturn($returnValue);
        }
        // Default parseStreamResult: parse NDJSON like ClaudeCliProvider does
        $mock->method('parseStreamResult')->willReturnCallback(function (?string $streamLog): array {
            $default = ['text' => '', 'session_id' => null, 'metadata' => []];
            if ($streamLog === null || $streamLog === '') {
                return $default;
            }

            $lines = explode("\n", $streamLog);
            $text = '';
            $sessionId = null;
            $metadata = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (($decoded['type'] ?? null) === 'system' && isset($decoded['session_id'])) {
                    $sessionId = $decoded['session_id'];
                    break;
                }
            }

            foreach (array_reverse($lines) as $line) {
                $line = trim($line);
                if ($line === '' || $line === '[DONE]') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (($decoded['type'] ?? null) === 'result') {
                    $text = $decoded['result'] ?? '';
                    $metadata['duration_ms'] = $decoded['duration_ms'] ?? null;
                    $metadata['num_turns'] = $decoded['num_turns'] ?? null;
                    $metadata['modelUsage'] = $decoded['modelUsage'] ?? null;
                    if ($sessionId === null && isset($decoded['session_id'])) {
                        $sessionId = $decoded['session_id'];
                    }
                    break;
                }
            }

            return ['text' => $text, 'session_id' => $sessionId, 'metadata' => $metadata];
        });
        return $mock;
    }

    public function testSubstitutesSlashCommandsWhenProviderDoesNotSupportThem(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);
        $run->prompt_markdown = 'Please run /deploy for this project';
        $run->save(false);

        $capturedPrompt = null;

        $mockProvider = $this->createConfigAwareStreamingProviderMock(
            supportsSlashCommands: false,
            callback: function ($prompt, $dir, $onLine) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;
                $onLine('{"type":"result","result":"Done"}');
                return ['exitCode' => 0, 'error' => ''];
            }
        );

        $commandContents = ['deploy' => "---\nname: deploy\n---\nDeploy the application to production.\n\n\$ARGUMENTS"];

        $job = $this->createJobWithProviderAndCommands($mockProvider, $commandContents);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($capturedPrompt)->stringNotContainsString('/deploy');
        verify($capturedPrompt)->stringContainsString('Deploy the application to production.');

        @unlink($run->getStreamFilePath());
    }

    public function testSkipsSubstitutionWhenProviderSupportsSlashCommands(): void
    {
        $run = $this->createRun(AiRunStatus::PENDING);
        $run->prompt_markdown = 'Please run /deploy for this project';
        $run->save(false);

        $capturedPrompt = null;

        $mockProvider = $this->createConfigAwareStreamingProviderMock(
            supportsSlashCommands: true,
            callback: function ($prompt, $dir, $onLine) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;
                $onLine('{"type":"result","result":"Done"}');
                return ['exitCode' => 0, 'error' => ''];
            }
        );

        $job = $this->createJobWithProvider($mockProvider);
        $job->runId = $run->id;

        $job->execute(null);

        $run->refresh();
        verify($run->status)->equals(AiRunStatus::COMPLETED->value);
        verify($capturedPrompt)->stringContainsString('/deploy');

        @unlink($run->getStreamFilePath());
    }

    private function createJobWithProvider(AiProviderInterface $provider): RunAiJob
    {
        $job = new class extends RunAiJob {
            public AiProviderInterface $mockProvider;

            protected function resolveProvider(AiRun $run): AiProviderInterface
            {
                return $this->mockProvider;
            }
        };
        $job->mockProvider = $provider;
        return $job;
    }

    private function createJobWithProviderAndHandler(AiProviderInterface $provider, AiQuickHandler $handler): RunAiJob
    {
        $job = new class extends RunAiJob {
            public AiProviderInterface $mockProvider;
            public AiQuickHandler $mockHandler;

            protected function resolveProvider(AiRun $run): AiProviderInterface
            {
                return $this->mockProvider;
            }

            protected function createQuickHandler(): AiQuickHandler
            {
                return $this->mockHandler;
            }
        };
        $job->mockProvider = $provider;
        $job->mockHandler = $handler;
        return $job;
    }

    /**
     * Creates a mock implementing AiProviderInterface, AiStreamingProviderInterface,
     * and AiConfigProviderInterface for testing the command substitution path.
     */
    private function createConfigAwareStreamingProviderMock(
        bool $supportsSlashCommands,
        ?callable $callback = null,
        ?array $returnValue = null,
    ): AiProviderInterface {
        $mock = $this->createMockForIntersectionOfInterfaces([
            AiProviderInterface::class,
            AiStreamingProviderInterface::class,
            AiConfigProviderInterface::class,
        ]);

        $mock->method('supportsSlashCommands')->willReturn($supportsSlashCommands);

        if ($callback !== null) {
            $mock->method('executeStreaming')->willReturnCallback($callback);
        } elseif ($returnValue !== null) {
            $mock->method('executeStreaming')->willReturn($returnValue);
        }

        $mock->method('parseStreamResult')->willReturnCallback(function (?string $streamLog): array {
            $default = ['text' => '', 'session_id' => null, 'metadata' => []];
            if ($streamLog === null || $streamLog === '') {
                return $default;
            }

            $lines = explode("\n", $streamLog);
            foreach (array_reverse($lines) as $line) {
                $line = trim($line);
                if ($line === '' || $line === '[DONE]') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (($decoded['type'] ?? null) === 'result') {
                    return ['text' => $decoded['result'] ?? '', 'session_id' => $decoded['session_id'] ?? null, 'metadata' => []];
                }
            }
            return $default;
        });

        return $mock;
    }

    /**
     * @param array<string, string> $commandContents
     */
    private function createJobWithProviderAndCommands(AiProviderInterface $provider, array $commandContents): RunAiJob
    {
        $job = new class extends RunAiJob {
            public AiProviderInterface $mockProvider;
            /** @var array<string, string> */
            public array $mockCommandContents = [];

            protected function resolveProvider(AiRun $run): AiProviderInterface
            {
                return $this->mockProvider;
            }

            protected function loadCommandContents(AiRun $run): array
            {
                return $this->mockCommandContents;
            }
        };
        $job->mockProvider = $provider;
        $job->mockCommandContents = $commandContents;
        return $job;
    }
}
