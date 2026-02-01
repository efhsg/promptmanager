<?php

namespace tests\unit\services;

use app\services\ClaudeCliService;
use Codeception\Test\Unit;
use ReflectionClass;
use Yii;

class ClaudeCliServiceTest extends Unit
{
    public function testBuildCommandWithoutSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['permissionMode' => 'plan'], null);

        $this->assertStringContainsString('claude --output-format json', $command);
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

    public function testParseJsonOutputExtractsSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Claude response',
            'is_error' => false,
            'total_cost_usd' => 0.05,
            'duration_ms' => 3000,
            'session_id' => 'extracted-session-123',
        ]);

        $result = $method->invoke($service, $jsonOutput);

        $this->assertSame('extracted-session-123', $result['session_id']);
        $this->assertSame('Claude response', $result['result']);
    }

    public function testParseJsonOutputExtractsTokensFromUsage(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Response',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_read_input_tokens' => 18000,
                'cache_creation_input_tokens' => 6000,
            ],
            'modelUsage' => [
                'claude-opus-4-5-20251101' => [
                    'inputTokens' => 100,
                    'outputTokens' => 50,
                    'cacheReadInputTokens' => 18000,
                    'cacheCreationInputTokens' => 6000,
                ],
            ],
        ]);

        $result = $method->invoke($service, $jsonOutput);

        $this->assertSame('opus-4.5', $result['model']);
        $this->assertSame(100, $result['input_tokens']);
        $this->assertSame(24000, $result['cache_tokens']);
        $this->assertSame(50, $result['output_tokens']);
    }

    public function testParseJsonOutputUsesTopLevelUsageNotCumulativeModelUsage(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        // usage = per-turn (last assistant message), modelUsage = cumulative session
        $jsonOutput = json_encode([
            'result' => 'Response',
            'usage' => [
                'input_tokens' => 67,
                'output_tokens' => 200,
                'cache_read_input_tokens' => 42000,
                'cache_creation_input_tokens' => 500,
            ],
            'modelUsage' => [
                'claude-sonnet-4-5-20250929' => [
                    'inputTokens' => 5000,
                    'outputTokens' => 3000,
                    'cacheReadInputTokens' => 120000,
                    'cacheCreationInputTokens' => 2000,
                    'contextWindow' => 200000,
                ],
            ],
        ]);

        $result = $method->invoke($service, $jsonOutput);

        // Token counts from top-level usage, NOT modelUsage
        $this->assertSame(67, $result['input_tokens']);
        $this->assertSame(42500, $result['cache_tokens']);
        $this->assertSame(200, $result['output_tokens']);
        // Model and contextWindow still from modelUsage
        $this->assertSame('sonnet-4.5', $result['model']);
        $this->assertSame(200000, $result['context_window']);
    }

    public function testParseJsonOutputExtractsContextWindow(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Response',
            'modelUsage' => [
                'claude-opus-4-5-20251101' => [
                    'inputTokens' => 500,
                    'outputTokens' => 100,
                    'contextWindow' => 200000,
                ],
            ],
        ]);

        $result = $method->invoke($service, $jsonOutput);

        $this->assertSame(200000, $result['context_window']);
    }

    public function testParseJsonOutputOmitsContextWindowWhenAbsent(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Response',
            'modelUsage' => [
                'claude-opus-4-5-20251101' => [
                    'inputTokens' => 500,
                    'outputTokens' => 100,
                ],
            ],
        ]);

        $result = $method->invoke($service, $jsonOutput);

        $this->assertArrayNotHasKey('context_window', $result);
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

    public function testParseJsonOutputHandlesMissingSessionId(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Response',
            'is_error' => false,
        ]);

        $result = $method->invoke($service, $jsonOutput);

        $this->assertArrayNotHasKey('session_id', $result);
    }

    public function testParseJsonOutputHandlesEmptyOutput(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $result = $method->invoke($service, '');

        $this->assertEmpty($result);
    }

    public function testParseJsonOutputHandlesInvalidJson(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not valid json');

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
}
