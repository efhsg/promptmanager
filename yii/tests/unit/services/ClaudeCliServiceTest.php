<?php

namespace tests\unit\services;

use app\services\ClaudeCliService;
use Codeception\Test\Unit;
use ReflectionClass;
use Yii;
use RuntimeException;
use app\services\ClaudeWorkspaceService;
use TypeError;

class ClaudeCliServiceTest extends Unit
{
    public function testBuildCommandWithoutSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], null);

        $this->assertStringContainsString("--output-format 'stream-json'", $command);
        $this->assertStringContainsString('--verbose', $command);
        $this->assertStringContainsString('--permission-mode', $command);
        $this->assertStringNotContainsString('--continue', $command);
    }

    public function testBuildCommandWithSessionIdPassesResumeFlag(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], 'test-session-abc123');

        $this->assertStringContainsString('--resume', $command);
        $this->assertStringNotContainsString('--continue', $command);
        $this->assertStringContainsString('test-session-abc123', $command);
    }

    public function testBuildCommandEscapesSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [], 'session; rm -rf /');

        $this->assertStringContainsString("--resume 'session; rm -rf /'", $command);
    }

    public function testParseStreamJsonOutputExtractsSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 1000, 'output_tokens' => 200])
            . "\n" . $this->buildResultLine([
                'session_id' => 'extracted-session-123',
                'result' => 'Claude response',
            ]);

        $result = $method->invoke($service, $ndjson);

        $this->assertSame('extracted-session-123', $result['session_id']);
        $this->assertSame('Claude response', $result['result']);
        $this->assertSame(3, $result['num_turns']);
    }

    public function testParseStreamJsonOutputExtractsTokensFromLastAssistant(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine([
            'input_tokens' => 100,
            'cache_read_input_tokens' => 18000,
            'cache_creation_input_tokens' => 6000,
            'output_tokens' => 3,
        ]) . "\n" . $this->buildResultLine([
            'usage' => ['output_tokens' => 50],
            'modelUsage' => [
                'claude-opus-4-5-20251101' => [
                    'inputTokens' => 100,
                    'outputTokens' => 50,
                    'contextWindow' => 200000,
                ],
            ],
        ]);

        $result = $method->invoke($service, $ndjson);

        $this->assertSame('opus-4.5', $result['model']);
        // Context fill from assistant message
        $this->assertSame(100, $result['input_tokens']);
        $this->assertSame(24000, $result['cache_tokens']);
        // Output tokens from result message (cumulative), not assistant (per-call)
        $this->assertSame(50, $result['output_tokens']);
    }

    public function testParseStreamJsonOutputUsesLastAssistantNotCumulative(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        // First assistant (tool-use cycle) — smaller context
        $ndjson = $this->buildAssistantLine([
            'input_tokens' => 20000,
            'output_tokens' => 500,
            'cache_read_input_tokens' => 15000,
            'cache_creation_input_tokens' => 1000,
        ]);
        // Second assistant (final response) — larger context
        $ndjson .= "\n" . $this->buildAssistantLine([
            'input_tokens' => 67,
            'output_tokens' => 200,
            'cache_read_input_tokens' => 42000,
            'cache_creation_input_tokens' => 500,
        ]);
        // Result with cumulative usage
        $ndjson .= "\n" . $this->buildResultLine([
            'num_turns' => 5,
            'usage' => ['input_tokens' => 87000, 'output_tokens' => 700],
            'modelUsage' => [
                'claude-sonnet-4-5-20250929' => [
                    'inputTokens' => 5000,
                    'outputTokens' => 3000,
                    'contextWindow' => 200000,
                ],
            ],
        ]);

        $result = $method->invoke($service, $ndjson);

        // Context fill from last assistant message, NOT cumulative
        $this->assertSame(67, $result['input_tokens']);
        $this->assertSame(42500, $result['cache_tokens']);
        // Output tokens from result message (cumulative total)
        $this->assertSame(700, $result['output_tokens']);
        // Model, contextWindow, and num_turns from result message
        $this->assertSame('sonnet-4.5', $result['model']);
        $this->assertSame(200000, $result['context_window']);
        $this->assertSame(5, $result['num_turns']);
    }

    public function testParseStreamJsonOutputExtractsContextWindow(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 500, 'output_tokens' => 100])
            . "\n" . $this->buildResultLine([
                'modelUsage' => [
                    'claude-opus-4-5-20251101' => [
                        'inputTokens' => 500,
                        'outputTokens' => 100,
                        'contextWindow' => 200000,
                    ],
                ],
            ]);

        $result = $method->invoke($service, $ndjson);

        $this->assertSame(200000, $result['context_window']);
    }

    public function testParseStreamJsonOutputOmitsContextWindowWhenAbsent(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 500, 'output_tokens' => 100])
            . "\n" . $this->buildResultLine([
                'modelUsage' => [
                    'claude-opus-4-5-20251101' => [
                        'inputTokens' => 500,
                        'outputTokens' => 100,
                    ],
                ],
            ]);

        $result = $method->invoke($service, $ndjson);

        $this->assertArrayNotHasKey('context_window', $result);
    }

    public function testParseStreamJsonOutputSkipsSidechainMessages(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        // Main assistant with real context usage
        $ndjson = $this->buildAssistantLine([
            'input_tokens' => 40000,
            'output_tokens' => 800,
            'cache_read_input_tokens' => 35000,
            'cache_creation_input_tokens' => 0,
        ]);
        // Sidechain assistant (parallel agent) — should be ignored
        $ndjson .= "\n" . $this->buildAssistantLine([
            'input_tokens' => 90000,
            'output_tokens' => 2000,
            'cache_read_input_tokens' => 80000,
            'cache_creation_input_tokens' => 5000,
        ], true);
        $ndjson .= "\n" . $this->buildResultLine([
            'usage' => ['output_tokens' => 800],
        ]);

        $result = $method->invoke($service, $ndjson);

        // Should use main assistant, not sidechain
        $this->assertSame(40000, $result['input_tokens']);
        $this->assertSame(35000, $result['cache_tokens']);
        $this->assertSame(800, $result['output_tokens']);
    }

    public function testParseStreamJsonOutputExtractsToolUses(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        // Assistant with tool_use content blocks
        $assistant = json_encode([
            'type' => 'assistant',
            'message' => [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me read that file.'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'Read', 'input' => ['file_path' => '/app/services/MyService.php']],
                    ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'Grep', 'input' => ['pattern' => 'class MyService']],
                ],
                'usage' => ['input_tokens' => 5000, 'output_tokens' => 100],
            ],
        ]);
        // Second assistant with another tool call
        $assistant2 = json_encode([
            'type' => 'assistant',
            'message' => [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'tool_use', 'id' => 'tu_3', 'name' => 'Edit', 'input' => ['file_path' => '/app/models/User.php']],
                ],
                'usage' => ['input_tokens' => 15000, 'output_tokens' => 200],
            ],
        ]);
        $ndjson = $assistant . "\n" . $assistant2 . "\n" . $this->buildResultLine();

        $result = $method->invoke($service, $ndjson);

        $this->assertCount(3, $result['tool_uses']);
        $this->assertSame('Read: /app/services/MyService.php', $result['tool_uses'][0]);
        $this->assertSame('Grep: class MyService', $result['tool_uses'][1]);
        $this->assertSame('Edit: /app/models/User.php', $result['tool_uses'][2]);
    }

    public function testParseStreamJsonOutputCountsTurnsWhenFieldMissing(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 10000, 'output_tokens' => 100])
            . "\n" . $this->buildAssistantLine(['input_tokens' => 20000, 'output_tokens' => 200])
            . "\n" . $this->buildAssistantLine(['input_tokens' => 30000, 'output_tokens' => 300])
            . "\n" . $this->buildResultLine(['num_turns' => null]);

        $result = $method->invoke($service, $ndjson);

        // Falls back to counting non-sidechain assistant messages
        $this->assertSame(3, $result['num_turns']);
    }

    public function testFormatModelNameShortensStandardIds(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatModelName');
        $method->setAccessible(true);

        $this->assertSame('opus-4.5', $method->invoke($service, 'claude-opus-4-5-20251101'));
        $this->assertSame('sonnet-4.0', $method->invoke($service, 'claude-sonnet-4-0-20250514'));
    }

    public function testFormatModelNameReturnsUnknownIdVerbatim(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatModelName');
        $method->setAccessible(true);

        $this->assertSame('custom-model', $method->invoke($service, 'custom-model'));
    }

    public function testParseStreamJsonOutputHandlesMissingSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 100, 'output_tokens' => 50])
            . "\n" . $this->buildResultLine(['session_id' => null]);

        $result = $method->invoke($service, $ndjson);

        $this->assertNull($result['session_id']);
    }

    public function testParseStreamJsonOutputHandlesEmptyOutput(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $result = $method->invoke($service, '');

        $this->assertEmpty($result);
    }

    public function testParseStreamJsonOutputHandlesNoResultMessage(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not valid json');

        $this->assertEmpty($result);
    }

    public function testParseStreamJsonOutputHandlesOnlyAssistantNoResult(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseStreamJsonOutput');
        $method->setAccessible(true);

        $ndjson = $this->buildAssistantLine(['input_tokens' => 1000, 'output_tokens' => 200]);

        $result = $method->invoke($service, $ndjson);

        $this->assertEmpty($result);
    }

    public function testCheckConfigReturnsNotMappedWhenNoMappings(): void
    {
        $service = new ClaudeCliService();

        $result = $service->checkClaudeConfigForPath('/nonexistent/host/path/project');

        $this->assertSame('not_mapped', $result['pathStatus']);
        $this->assertFalse($result['pathMapped']);
        $this->assertFalse($result['hasAnyConfig']);
        $this->assertSame('/nonexistent/host/path/project', $result['requestedPath']);
        $this->assertSame('/nonexistent/host/path/project', $result['effectivePath']);
    }

    public function testCheckConfigReturnsNotAccessibleWhenMappedButMissing(): void
    {
        // Configure a mapping that translates to a nonexistent container path
        $originalMappings = Yii::$app->params['pathMappings'] ?? [];
        Yii::$app->params['pathMappings'] = ['/host/projects' => '/nonexistent/container/path'];

        try {
            $service = new ClaudeCliService();
            $result = $service->checkClaudeConfigForPath('/host/projects/myapp');

            $this->assertSame('not_accessible', $result['pathStatus']);
            $this->assertTrue($result['pathMapped']);
            $this->assertFalse($result['hasAnyConfig']);
            $this->assertSame('/host/projects/myapp', $result['requestedPath']);
            $this->assertSame('/nonexistent/container/path/myapp', $result['effectivePath']);
        } finally {
            Yii::$app->params['pathMappings'] = $originalMappings;
        }
    }

    public function testCheckConfigReturnsHasConfigWhenConfigExists(): void
    {
        // Use a temp directory with a CLAUDE.md file
        $tmpDir = sys_get_temp_dir() . '/claude_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/CLAUDE.md', '# Test');

        // Map directly so translatePath is identity (path equals container path)
        try {
            $service = new ClaudeCliService();
            $result = $service->checkClaudeConfigForPath($tmpDir);

            $this->assertSame('has_config', $result['pathStatus']);
            $this->assertTrue($result['hasCLAUDE_MD']);
            $this->assertTrue($result['hasAnyConfig']);
        } finally {
            @unlink($tmpDir . '/CLAUDE.md');
            @rmdir($tmpDir);
        }
    }

    public function testCheckConfigReturnsNoConfigWhenDirExistsButEmpty(): void
    {
        $tmpDir = sys_get_temp_dir() . '/claude_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        try {
            $service = new ClaudeCliService();
            $result = $service->checkClaudeConfigForPath($tmpDir);

            $this->assertSame('no_config', $result['pathStatus']);
            $this->assertFalse($result['hasAnyConfig']);
        } finally {
            @rmdir($tmpDir);
        }
    }

    public function testBuildCommandWithStreamingFlagAddsPartialMessages(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], null, true);

        $this->assertStringContainsString('--include-partial-messages', $command);
        $this->assertStringContainsString("--output-format 'stream-json'", $command);
        $this->assertStringContainsString('--verbose', $command);
    }

    public function testBuildCommandWithoutStreamingFlagOmitsPartialMessages(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], null, false);

        $this->assertStringNotContainsString('--include-partial-messages', $command);
        $this->assertStringContainsString("--output-format 'stream-json'", $command);
    }

    public function testBuildCommandWithTextFormatOmitsVerbose(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [
            'outputFormat' => 'text',
            'verbose' => false,
            'permissionMode' => 'plan',
        ], null);

        $this->assertStringContainsString("--output-format 'text'", $command);
        $this->assertStringNotContainsString('--verbose', $command);
    }

    public function testBuildCommandWithJsonFormatOmitsVerbose(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [
            'outputFormat' => 'json',
            'verbose' => false,
        ], null);

        $this->assertStringContainsString("--output-format 'json'", $command);
        $this->assertStringNotContainsString('--verbose', $command);
    }

    public function testParseJsonOutputExtractsResult(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $json = json_encode([
            'result' => 'Refactor authentication to use JWT',
            'is_error' => false,
            'duration_ms' => 1500,
            'session_id' => 'sess-123',
            'num_turns' => 1,
        ]);

        $result = $method->invoke($service, $json);

        $this->assertSame('Refactor authentication to use JWT', $result['result']);
        $this->assertFalse($result['is_error']);
        $this->assertSame(1500, $result['duration_ms']);
        $this->assertSame('sess-123', $result['session_id']);
        $this->assertSame(1, $result['num_turns']);
    }

    public function testParseJsonOutputHandlesEmptyOutput(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $this->assertEmpty($method->invoke($service, ''));
    }

    public function testParseJsonOutputHandlesInvalidJson(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $this->assertEmpty($method->invoke($service, 'not json'));
    }

    public function testBuildCommandWithQuickHandlerOptions(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [
            'model' => 'haiku',
            'systemPromptFile' => '/path/to/prompt.md',
            'maxTurns' => 1,
            'outputFormat' => 'json',
            'verbose' => false,
            'tools' => '',
            'noSessionPersistence' => true,
        ], null);

        $this->assertStringContainsString("--output-format 'json'", $command);
        $this->assertStringNotContainsString('--verbose', $command);
        $this->assertStringNotContainsString('--permission-mode', $command);
        $this->assertStringContainsString('--no-session-persistence', $command);
        $this->assertStringContainsString("--tools ''", $command);
        $this->assertStringContainsString("--model 'haiku'", $command);
        $this->assertStringContainsString("--system-prompt-file '/path/to/prompt.md'", $command);
        $this->assertStringContainsString('--max-turns 1', $command);
        $this->assertStringContainsString('-p -', $command);
    }

    public function testBuildCommandOmitsPermissionModeWhenNotSet(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['model' => 'haiku'], null);

        $this->assertStringNotContainsString('--permission-mode', $command);
    }

    public function testBuildCommandSystemPromptFileTakesPrecedenceOverSystemPrompt(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [
            'systemPromptFile' => '/path/to/file.md',
            'systemPrompt' => 'Inline prompt',
        ], null);

        $this->assertStringContainsString('--system-prompt-file', $command);
        $this->assertStringNotContainsString("--system-prompt 'Inline", $command);
    }

    public function testBuildCommandAlwaysAppendsNoQuestionsInstruction(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        // Without any system prompt options
        $command = $method->invoke($service, [], null);
        $this->assertStringContainsString('--append-system-prompt', $command);
        $this->assertStringContainsString('Do not ask the user any questions', $command);

        // With systemPromptFile — append-system-prompt should still be present
        $command = $method->invoke($service, [
            'systemPromptFile' => '/path/to/prompt.md',
        ], null);
        $this->assertStringContainsString('--system-prompt-file', $command);
        $this->assertStringContainsString('--append-system-prompt', $command);
        $this->assertStringContainsString('Do not ask the user any questions', $command);

        // With user-provided appendSystemPrompt — both should be combined
        $command = $method->invoke($service, [
            'appendSystemPrompt' => 'Custom instruction here',
        ], null);
        $this->assertStringContainsString('Custom instruction here', $command);
        $this->assertStringContainsString('Do not ask the user any questions', $command);
        // Should be a single --append-system-prompt flag (combined)
        $this->assertSame(1, substr_count($command, '--append-system-prompt'));
    }

    public function testExecuteStreamingThrowsWhenDirectoryMissing(): void
    {
        $mockWorkspace = $this->createMock(ClaudeWorkspaceService::class);
        $mockWorkspace->method('getDefaultWorkspacePath')
            ->willReturn('/nonexistent/workspace/' . uniqid());

        $service = new ClaudeCliService(null, $mockWorkspace);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Working directory does not exist');

        $service->executeStreaming(
            'test prompt',
            '/nonexistent/path/' . uniqid(),
            function (string $line): void {},
        );
    }

    public function testCancelRunningProcessThrowsTypeErrorWhenNoStreamToken(): void
    {
        $service = new ClaudeCliService();
        $this->expectException(TypeError::class);
        $service->cancelRunningProcess(null);
    }

    public function testCancelRunningProcessReturnsFalseWhenNoPidInCache(): void
    {
        $service = new ClaudeCliService();
        $result = $service->cancelRunningProcess('nonexistent-token');

        $this->assertFalse($result);
    }

    public function testStoreAndClearProcessPidViaReflection(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);

        $token = 'test-token-abc';
        $storeMethod = $reflection->getMethod('storeProcessPid');
        $storeMethod->setAccessible(true);
        $storeMethod->invoke($service, 99999, $token);

        $cacheKey = 'claude_cli_pid_' . Yii::$app->user->id . '_' . $token;
        $this->assertSame(99999, Yii::$app->cache->get($cacheKey));

        $clearMethod = $reflection->getMethod('clearProcessPid');
        $clearMethod->setAccessible(true);
        $clearMethod->invoke($service, $token);

        $this->assertFalse(Yii::$app->cache->get($cacheKey));
    }

    public function testStoreProcessPidWithStreamTokenUsesTokenInKey(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);

        $storeMethod = $reflection->getMethod('storeProcessPid');
        $storeMethod->setAccessible(true);
        $storeMethod->invoke($service, 11111, 'token-abc');

        $userId = Yii::$app->user->id;
        $scopedKey = 'claude_cli_pid_' . $userId . '_token-abc';
        $globalKey = 'claude_cli_pid_' . $userId;

        $this->assertSame(11111, Yii::$app->cache->get($scopedKey));
        $this->assertFalse(Yii::$app->cache->get($globalKey));

        $clearMethod = $reflection->getMethod('clearProcessPid');
        $clearMethod->setAccessible(true);
        $clearMethod->invoke($service, 'token-abc');

        $this->assertFalse(Yii::$app->cache->get($scopedKey));
    }

    public function testConcurrentStreamTokensDoNotCollide(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);

        $storeMethod = $reflection->getMethod('storeProcessPid');
        $storeMethod->setAccessible(true);

        $storeMethod->invoke($service, 11111, 'token-a');
        $storeMethod->invoke($service, 22222, 'token-b');

        $userId = Yii::$app->user->id;

        $this->assertSame(11111, Yii::$app->cache->get('claude_cli_pid_' . $userId . '_token-a'));
        $this->assertSame(22222, Yii::$app->cache->get('claude_cli_pid_' . $userId . '_token-b'));

        $clearMethod = $reflection->getMethod('clearProcessPid');
        $clearMethod->setAccessible(true);
        $clearMethod->invoke($service, 'token-a');

        $this->assertFalse(Yii::$app->cache->get('claude_cli_pid_' . $userId . '_token-a'));
        $this->assertSame(22222, Yii::$app->cache->get('claude_cli_pid_' . $userId . '_token-b'));

        $clearMethod->invoke($service, 'token-b');
        $this->assertFalse(Yii::$app->cache->get('claude_cli_pid_' . $userId . '_token-b'));
    }

    public function testBuildPidCacheKeyNeverProducesSharedKey(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPidCacheKey');
        $method->setAccessible(true);

        $userId = Yii::$app->user->id;
        $sharedKey = 'claude_cli_pid_' . $userId;

        // Any token should produce a key that is NOT the shared (token-less) key
        $key = $method->invoke($service, 'any-token');
        $this->assertNotSame($sharedKey, $key);
        $this->assertStringStartsWith($sharedKey . '_', $key);
    }

    public function testExecuteStreamingGeneratesFallbackTokenWhenNull(): void
    {
        $mockWorkspace = $this->createMock(ClaudeWorkspaceService::class);
        $mockWorkspace->method('getDefaultWorkspacePath')
            ->willReturn('/nonexistent/workspace/' . uniqid());

        $service = new ClaudeCliService(null, $mockWorkspace);
        $userId = Yii::$app->user->id;
        $sharedKey = 'claude_cli_pid_' . $userId;

        // executeStreaming with null streamToken should NOT store under the shared key.
        // It will throw because the directory doesn't exist, but the fallback token
        // generation happens before the directory check.
        try {
            $service->executeStreaming(
                'test prompt',
                '/nonexistent/path/' . uniqid(),
                function (string $line): void {},
                3600,
                [],
                null,
                null,
                null // explicitly null streamToken
            );
        } catch (RuntimeException) {
            // expected — directory doesn't exist
        }

        // The shared key must NOT have been written
        $this->assertFalse(Yii::$app->cache->get($sharedKey));
    }

    public function testLoadCommandsFromDirectoryReturnsEmptyWhenNull(): void
    {
        $service = new ClaudeCliService();
        $this->assertSame([], $service->loadCommandsFromDirectory(null));
    }

    public function testLoadCommandsFromDirectoryReturnsEmptyWhenBlank(): void
    {
        $service = new ClaudeCliService();
        $this->assertSame([], $service->loadCommandsFromDirectory('   '));
    }

    public function testLoadCommandsFromDirectoryReturnsEmptyWhenNoCommandsDir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/claude_cmd_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        try {
            $service = new ClaudeCliService();
            $this->assertSame([], $service->loadCommandsFromDirectory($tmpDir));
        } finally {
            @rmdir($tmpDir);
        }
    }

    public function testLoadCommandsFromDirectoryScansMarkdownFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/claude_cmd_test_' . uniqid();
        $commandsDir = $tmpDir . '/.claude/commands';
        mkdir($commandsDir, 0o755, true);

        file_put_contents($commandsDir . '/review.md', "---\ndescription: Review code changes\n---\n# Review");
        file_put_contents($commandsDir . '/deploy.md', "---\ndescription: Deploy to production\n---\n# Deploy");
        file_put_contents($commandsDir . '/plain.md', "# No frontmatter here");

        try {
            $service = new ClaudeCliService();
            $result = $service->loadCommandsFromDirectory($tmpDir);

            $this->assertCount(3, $result);
            // Sorted alphabetically
            $keys = array_keys($result);
            $this->assertSame(['deploy', 'plain', 'review'], $keys);
            $this->assertSame('Deploy to production', $result['deploy']);
            $this->assertSame('Review code changes', $result['review']);
            $this->assertSame('', $result['plain']);
        } finally {
            @unlink($commandsDir . '/review.md');
            @unlink($commandsDir . '/deploy.md');
            @unlink($commandsDir . '/plain.md');
            @rmdir($commandsDir);
            @rmdir($tmpDir . '/.claude');
            @rmdir($tmpDir);
        }
    }

    public function testParseCommandDescriptionExtractsFromFrontmatter(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseCommandDescription');
        $method->setAccessible(true);

        $tmpFile = tempnam(sys_get_temp_dir(), 'cmd_');
        file_put_contents($tmpFile, "---\nname: test\ndescription: Run all tests\n---\n# Test command");

        try {
            $this->assertSame('Run all tests', $method->invoke($service, $tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testParseCommandDescriptionReturnsEmptyWhenNoFrontmatter(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseCommandDescription');
        $method->setAccessible(true);

        $tmpFile = tempnam(sys_get_temp_dir(), 'cmd_');
        file_put_contents($tmpFile, "# Just a heading\nSome content.");

        try {
            $this->assertSame('', $method->invoke($service, $tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetGitBranchReturnsNullWhenDirectoryDoesNotExist(): void
    {
        $service = new ClaudeCliService();
        $this->assertNull($service->getGitBranch('/nonexistent/path/' . uniqid()));
    }

    public function testGetGitBranchReturnsNullWhenNotAGitRepo(): void
    {
        $tmpDir = sys_get_temp_dir() . '/claude_git_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        try {
            $service = new ClaudeCliService();
            $this->assertNull($service->getGitBranch($tmpDir));
        } finally {
            @rmdir($tmpDir);
        }
    }

    public function testGetGitBranchReturnsBranchNameForGitRepo(): void
    {
        exec('git --version 2>/dev/null', $versionOut, $versionExitCode);
        if ($versionExitCode !== 0) {
            $this->markTestSkipped('git is not available in the test environment.');
        }

        $originalMappings = Yii::$app->params['pathMappings'] ?? [];
        $tmpDir = sys_get_temp_dir() . '/claude_git_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        exec('git -C ' . escapeshellarg($tmpDir) . ' init -b test-branch 2>/dev/null', $out, $exitCode);
        if ($exitCode !== 0) {
            // Older git without -b flag
            exec('git -C ' . escapeshellarg($tmpDir) . ' init 2>/dev/null');
            exec('git -C ' . escapeshellarg($tmpDir) . ' checkout -b test-branch 2>/dev/null', $out, $exitCode);
        }

        if ($exitCode !== 0) {
            $this->removeDirectory($tmpDir);
            $this->markTestSkipped('git init failed in the test environment.');
        }

        try {
            file_put_contents($tmpDir . '/README.md', 'test');
            exec('git -C ' . escapeshellarg($tmpDir) . ' add README.md 2>/dev/null');
            exec(
                'git -C ' . escapeshellarg($tmpDir)
                . ' -c user.email=test@example.com -c user.name=test commit -m "init" 2>/dev/null',
                $out,
                $exitCode
            );
            if ($exitCode !== 0) {
                $this->markTestSkipped('git commit failed in the test environment.');
            }

            $tempRoot = sys_get_temp_dir();
            Yii::$app->params['pathMappings'] = [$tempRoot => $tempRoot];
            $service = new ClaudeCliService();
            $branch = $service->getGitBranch($tmpDir);
            $this->assertSame('test-branch', $branch);
        } finally {
            // Clean up .git directory and tmpDir
            $this->removeDirectory($tmpDir);
            Yii::$app->params['pathMappings'] = $originalMappings;
        }
    }

    public function testGetSubscriptionUsageReturnsErrorWhenCredentialsMissing(): void
    {
        $path = sys_get_temp_dir() . '/claude_credentials_missing_' . uniqid() . '.json';
        $service = new ClaudeCliService();

        $this->withCredentialsPath($path, function () use ($service) {
            $result = $service->getSubscriptionUsage();
            $this->assertFalse($result['success']);
            $this->assertSame('Claude credentials file not found', $result['error']);
        });
    }

    public function testGetSubscriptionUsageReturnsErrorWhenTokenMissing(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'claude_credentials_');
        file_put_contents($tmpFile, json_encode(['claudeAiOauth' => []]));
        $service = new ClaudeCliService();

        try {
            $this->withCredentialsPath($tmpFile, function () use ($service) {
                $result = $service->getSubscriptionUsage();
                $this->assertFalse($result['success']);
                $this->assertSame('No OAuth access token found in credentials', $result['error']);
            });
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetSubscriptionUsageReturnsErrorWhenApiNon200(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'claude_credentials_');
        file_put_contents($tmpFile, json_encode(['claudeAiOauth' => ['accessToken' => 'token']]));

        $service = new class () extends ClaudeCliService {
            protected function fetchSubscriptionUsage(string $accessToken): array
            {
                return ['success' => true, 'status' => 429, 'body' => 'Rate limit'];
            }
        };

        try {
            $this->withCredentialsPath($tmpFile, function () use ($service) {
                $result = $service->getSubscriptionUsage();
                $this->assertFalse($result['success']);
                $this->assertSame('API returned HTTP 429', $result['error']);
            });
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetSubscriptionUsageNormalizesUsageWindows(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'claude_credentials_');
        file_put_contents($tmpFile, json_encode(['claudeAiOauth' => ['accessToken' => 'token']]));

        $payload = json_encode([
            'five_hour' => ['utilization' => 12.5, 'resets_at' => '2026-02-05T10:00:00Z'],
            'seven_day' => ['utilization' => 45, 'resets_at' => null],
            'seven_day_opus' => ['utilization' => 80, 'resets_at' => '2026-02-10T00:00:00Z'],
            'seven_day_sonnet' => ['utilization' => 55, 'resets_at' => null],
            'ignored_window' => ['utilization' => 100, 'resets_at' => null],
        ]);

        $service = new class ($payload) extends ClaudeCliService {
            private string $payload;

            public function __construct(string $payload)
            {
                parent::__construct();
                $this->payload = $payload;
            }

            protected function fetchSubscriptionUsage(string $accessToken): array
            {
                return ['success' => true, 'status' => 200, 'body' => $this->payload];
            }
        };

        try {
            $this->withCredentialsPath($tmpFile, function () use ($service) {
                $result = $service->getSubscriptionUsage();
                $this->assertTrue($result['success']);

                $windows = $result['data']['windows'] ?? [];
                $this->assertCount(4, $windows);

                $byKey = [];
                foreach ($windows as $window) {
                    $this->assertArrayHasKey('key', $window);
                    $this->assertArrayHasKey('label', $window);
                    $this->assertArrayHasKey('utilization', $window);
                    $this->assertArrayHasKey('resets_at', $window);
                    $this->assertIsString($window['label']);
                    $this->assertNotSame('', $window['label']);
                    $byKey[$window['key']] = $window;
                }

                $this->assertSame(12.5, $byKey['five_hour']['utilization']);
                $this->assertSame('2026-02-05T10:00:00Z', $byKey['five_hour']['resets_at']);

                $this->assertSame(45.0, $byKey['seven_day']['utilization']);
                $this->assertNull($byKey['seven_day']['resets_at']);

                $this->assertSame(80.0, $byKey['seven_day_opus']['utilization']);
                $this->assertSame('2026-02-10T00:00:00Z', $byKey['seven_day_opus']['resets_at']);

                $this->assertSame(55.0, $byKey['seven_day_sonnet']['utilization']);
                $this->assertNull($byKey['seven_day_sonnet']['resets_at']);
            });
        } finally {
            @unlink($tmpFile);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function withCredentialsPath(?string $path, callable $callback): void
    {
        $hadKey = array_key_exists('claudeCredentialsPath', Yii::$app->params);
        $original = $hadKey ? Yii::$app->params['claudeCredentialsPath'] : null;
        Yii::$app->params['claudeCredentialsPath'] = $path;

        try {
            $callback();
        } finally {
            if ($hadKey) {
                Yii::$app->params['claudeCredentialsPath'] = $original;
            } else {
                unset(Yii::$app->params['claudeCredentialsPath']);
            }
        }
    }

    private function buildAssistantLine(array $usage, bool $isSidechain = false): string
    {
        $line = [
            'type' => 'assistant',
            'message' => [
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Response']],
                'usage' => $usage,
            ],
        ];
        if ($isSidechain) {
            $line['isSidechain'] = true;
        }
        return json_encode($line);
    }

    private function buildResultLine(array $overrides = []): string
    {
        return json_encode(array_merge([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'result' => 'Claude response',
            'duration_ms' => 3000,
            'session_id' => 'test-session-123',
            'num_turns' => 3,
            'total_cost_usd' => 0.05,
            'usage' => [],
            'modelUsage' => [],
        ], $overrides));
    }
}
