<?php

namespace tests\unit\services;

use app\services\ClaudeCliCompletionClient;
use app\services\ClaudeCliService;
use Codeception\Test\Unit;

class ClaudeCliCompletionClientTest extends Unit
{
    public function testCompleteReturnsOutputOnSuccess(): void
    {
        $service = $this->createMock(ClaudeCliService::class);
        $service->method('execute')->willReturn([
            'success' => true,
            'output' => 'Refactor auth to JWT',
            'error' => '',
            'exitCode' => 0,
        ]);

        $client = new ClaudeCliCompletionClient($service);
        $result = $client->complete('some prompt', '/tmp/system.md', ['model' => 'haiku']);

        $this->assertTrue($result['success']);
        $this->assertSame('Refactor auth to JWT', $result['output']);
    }

    public function testCompleteReturnsErrorOnFailure(): void
    {
        $service = $this->createMock(ClaudeCliService::class);
        $service->method('execute')->willReturn([
            'success' => false,
            'output' => '',
            'error' => 'Timed out',
            'exitCode' => 124,
        ]);

        $client = new ClaudeCliCompletionClient($service);
        $result = $client->complete('some prompt', '/tmp/system.md');

        $this->assertFalse($result['success']);
        $this->assertSame('Timed out', $result['error']);
    }

    public function testCompleteReturnsErrorWhenOutputEmpty(): void
    {
        $service = $this->createMock(ClaudeCliService::class);
        $service->method('execute')->willReturn([
            'success' => true,
            'output' => '',
            'error' => '',
            'exitCode' => 0,
        ]);

        $client = new ClaudeCliCompletionClient($service);
        $result = $client->complete('some prompt', '/tmp/system.md');

        $this->assertFalse($result['success']);
        $this->assertSame('AI returned empty output.', $result['error']);
    }

    public function testCompletePassesIsolatedOptionsToService(): void
    {
        $service = $this->createMock(ClaudeCliService::class);
        $service->expects($this->once())
            ->method('execute')
            ->with(
                'my prompt',
                $this->callback(fn(string $dir) => is_dir($dir)),
                90,
                $this->callback(function (array $opts) {
                    return $opts['model'] === 'sonnet'
                        && $opts['maxTurns'] === 1
                        && $opts['outputFormat'] === 'json'
                        && $opts['verbose'] === false
                        && $opts['tools'] === ''
                        && $opts['noSessionPersistence'] === true
                        && $opts['rawWorkingDirectory'] === true
                        && $opts['systemPromptFile'] === '/tmp/sys.md';
                }),
                null,
                null
            )
            ->willReturn([
                'success' => true,
                'output' => 'Title',
                'error' => '',
                'exitCode' => 0,
            ]);

        $client = new ClaudeCliCompletionClient($service);
        $client->complete('my prompt', '/tmp/sys.md', ['model' => 'sonnet', 'timeout' => 90]);
    }

    public function testCompleteTrimsOutput(): void
    {
        $service = $this->createMock(ClaudeCliService::class);
        $service->method('execute')->willReturn([
            'success' => true,
            'output' => "  Trimmed title  \n",
            'error' => '',
            'exitCode' => 0,
        ]);

        $client = new ClaudeCliCompletionClient($service);
        $result = $client->complete('prompt', '/tmp/system.md');

        $this->assertSame('Trimmed title', $result['output']);
    }
}
