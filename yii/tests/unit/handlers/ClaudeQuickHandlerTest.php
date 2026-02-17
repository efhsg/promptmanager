<?php

namespace tests\unit\handlers;

use app\handlers\AiQuickHandler;
use app\services\AiCompletionClient;
use Codeception\Test\Unit;
use InvalidArgumentException;

class AiQuickHandlerTest extends Unit
{
    public function testRunReturnsOutputOnSuccess(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => 'Refactor authentication to use JWT',
        ]);

        $handler = new AiQuickHandler($client);
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

        $handler = new AiQuickHandler($client);
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

        $handler = new AiQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertFalse($result['success']);
        $this->assertSame('Command timed out after 60 seconds', $result['error']);
    }

    public function testRunThrowsForUnknownUseCase(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $handler = new AiQuickHandler($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown use case: nonexistent');

        $handler->run('nonexistent', 'some prompt');
    }

    public function testRunSkipsShortPrompts(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->never())->method('complete');

        $handler = new AiQuickHandler($client);
        $result = $handler->run('prompt-title', 'short');

        $this->assertFalse($result['success']);
        $this->assertSame('Prompt too short for summarization.', $result['error']);
    }

    public function testRunTruncatesLongPrompts(): void
    {
        $longPrompt = str_repeat('a', 3500);

        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->once())
            ->method('complete')
            ->with(
                $this->callback(function (string $p) {
                    // 3000 chars content + <document></document> wrapper
                    return str_starts_with($p, '<document>')
                        && str_ends_with($p, '</document>')
                        && mb_strlen($p) === 3000 + strlen('<document></document>');
                }),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([
                'success' => true,
                'output' => 'Truncated title',
            ]);

        $handler = new AiQuickHandler($client);
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
                    return $opts['model'] === 'sonnet'
                        && $opts['timeout'] === 60;
                })
            )
            ->willReturn([
                'success' => true,
                'output' => 'Test title',
            ]);

        $handler = new AiQuickHandler($client);
        $handler->run('prompt-title', str_repeat('x', 150));
    }

    public function testRunNoteNameReturnsGeneratedName(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => 'JWT authentication refactoring plan',
        ]);

        $handler = new AiQuickHandler($client);
        $result = $handler->run('note-name', str_repeat('a', 30));

        $this->assertTrue($result['success']);
        $this->assertSame('JWT authentication refactoring plan', $result['output']);
    }

    public function testRunNoteNameRespectsMinChars(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->never())->method('complete');

        $handler = new AiQuickHandler($client);
        $result = $handler->run('note-name', 'short text');

        $this->assertFalse($result['success']);
        $this->assertSame('Prompt too short for summarization.', $result['error']);
    }

    public function testRunNoteNameTruncatesAtMaxChars(): void
    {
        $longPrompt = str_repeat('a', 5500);

        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->once())
            ->method('complete')
            ->with(
                $this->callback(function (string $p) {
                    return str_starts_with($p, '<document>')
                        && str_ends_with($p, '</document>')
                        && mb_strlen($p) === 5000 + strlen('<document></document>');
                }),
                $this->callback(fn(string $f) => str_ends_with($f, '/note-name/CLAUDE.md')),
                $this->anything()
            )
            ->willReturn([
                'success' => true,
                'output' => 'Truncated name',
            ]);

        $handler = new AiQuickHandler($client);
        $result = $handler->run('note-name', $longPrompt);

        $this->assertTrue($result['success']);
    }

    public function testRunPromptInstanceLabelReturnsGeneratedLabel(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => 'Review authentication session handling',
        ]);

        $handler = new AiQuickHandler($client);
        $result = $handler->run('prompt-instance-label', str_repeat('a', 150));

        $this->assertTrue($result['success']);
        $this->assertSame('Review authentication session handling', $result['output']);
    }

    public function testRunPromptInstanceLabelRespectsMinChars(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->never())->method('complete');

        $handler = new AiQuickHandler($client);
        $result = $handler->run('prompt-instance-label', 'short');

        $this->assertFalse($result['success']);
        $this->assertSame('Prompt too short for summarization.', $result['error']);
    }

    public function testRunPromptInstanceLabelPassesCorrectWorkdir(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->expects($this->once())
            ->method('complete')
            ->with(
                $this->anything(),
                $this->callback(fn(string $f) => str_ends_with($f, '/prompt-instance-label/CLAUDE.md')),
                $this->callback(fn(array $opts) => $opts['model'] === 'sonnet')
            )
            ->willReturn([
                'success' => true,
                'output' => 'Test label',
            ]);

        $handler = new AiQuickHandler($client);
        $handler->run('prompt-instance-label', str_repeat('x', 150));
    }

    public function testRunTrimsOutputWhitespace(): void
    {
        $client = $this->createMock(AiCompletionClient::class);
        $client->method('complete')->willReturn([
            'success' => true,
            'output' => "  Title with spaces  \n",
        ]);

        $handler = new AiQuickHandler($client);
        $result = $handler->run('prompt-title', str_repeat('a', 150));

        $this->assertSame('Title with spaces', $result['output']);
    }
}
