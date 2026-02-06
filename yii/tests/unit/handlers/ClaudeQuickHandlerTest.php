<?php

namespace tests\unit\handlers;

use app\handlers\ClaudeQuickHandler;
use app\services\AiCompletionClient;
use Codeception\Test\Unit;
use InvalidArgumentException;

class ClaudeQuickHandlerTest extends Unit
{
    public function testRunReturnsOutputOnSuccess(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => 'Refactor authentication to use JWT',
        ]);

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertTrue($result['success']);
        $this->assertSame('Refactor authentication to use JWT', $result['output']);
    }

    public function testRunReturnsErrorWhenOutputEmpty(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => false,
            'error' => 'AI returned empty output.',
        ]);

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertFalse($result['success']);
        $this->assertSame('AI returned empty output.', $result['error']);
    }

    public function testRunReturnsErrorWhenExecuteFails(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => false,
            'error' => 'Command timed out after 60 seconds',
        ]);

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertFalse($result['success']);
        $this->assertSame('Command timed out after 60 seconds', $result['error']);
    }

    public function testRunThrowsForUnknownUseCase(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $handler = new ClaudeQuickHandler($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown use case: nonexistent');

        $handler->run('nonexistent', 'some prompt');
    }

    public function testRunSkipsShortPrompts(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->never())->method('complete');

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', 'short');

        $this->assertFalse($result['success']);
        $this->assertSame('Prompt too short for summarization.', $result['error']);
    }

    public function testRunTruncatesLongPrompts(): void
    {
        $longPrompt = str_repeat('a', 600);

        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->once())
            ->method('complete')
            ->with(
                $this->callback(function (string $p) {
                    // 500 chars content + <document></document> wrapper
                    return str_starts_with($p, '<document>')
                        && str_ends_with($p, '</document>')
                        && mb_strlen($p) === 500 + strlen('<document></document>');
                }),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([
                'success' => true,
                'output' => 'Truncated title',
            ]);

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', $longPrompt);

        $this->assertTrue($result['success']);
    }

    public function testRunPassesCorrectOptionsToClient(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->once())
            ->method('complete')
            ->with(
                $this->callback(fn(string $p) => str_starts_with($p, '<document>') && str_ends_with($p, '</document>')),
                $this->callback(fn(string $f) => str_ends_with($f, '/prompt-title/CLAUDE.md')),
                $this->callback(function (array $opts) {
                    return $opts['model'] === 'haiku'
                        && $opts['timeout'] === 60;
                })
            )
            ->willReturn([
                'success' => true,
                'output' => 'Test title',
            ]);

        $handler = new ClaudeQuickHandler($client);
        $handler->run('prompt-title', str_repeat('x', 150));
    }

    public function testRunTrimsOutputWhitespace(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => "  Title with spaces  \n",
        ]);

        $handler = new ClaudeQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertSame('Title with spaces', $result['output']);
    }
}
