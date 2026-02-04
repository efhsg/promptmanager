<?php

namespace tests\unit\services;

use app\services\ClaudeCliService;
use Codeception\Test\Unit;
use ReflectionClass;
use Yii;
use RuntimeException;
use app\services\ClaudeWorkspaceService;

class ClaudeCliServiceTest extends Unit
{
    public function testBuildCommandWithoutSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], null);

        $this->assertStringContainsString('claude --output-format stream-json --verbose', $command);
        $this->assertStringContainsString('--permission-mode', $command);
        $this->assertStringNotContainsString('--continue', $command);
    }

    public function testBuildCommandWithSessionIdPassesContinueFlag(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], 'test-session-abc123');

        $this->assertStringContainsString('--continue', $command);
        $this->assertStringContainsString('test-session-abc123', $command);
    }

    public function testBuildCommandEscapesSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [], 'session; rm -rf /');

        $this->assertStringContainsString("--continue 'session; rm -rf /'", $command);
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
        $this->assertStringContainsString('--output-format stream-json', $command);
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
        $this->assertStringContainsString('--output-format stream-json', $command);
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

    public function testCancelRunningProcessReturnsFalseWhenNoPid(): void
    {
        $service = new ClaudeCliService();
        $result = $service->cancelRunningProcess();

        $this->assertFalse($result);
    }

    public function testStoreAndClearProcessPidViaReflection(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);

        $storeMethod = $reflection->getMethod('storeProcessPid');
        $storeMethod->setAccessible(true);
        $storeMethod->invoke($service, 99999);

        $cacheKey = 'claude_cli_pid_' . Yii::$app->user->id;
        $this->assertSame(99999, Yii::$app->cache->get($cacheKey));

        $clearMethod = $reflection->getMethod('clearProcessPid');
        $clearMethod->setAccessible(true);
        $clearMethod->invoke($service);

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
