<?php

namespace tests\unit\services;

use app\services\ClaudeCliService;
use Codeception\Test\Unit;
use ReflectionClass;

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

    public function testParseJsonOutputExtractsModelUsage(): void
    {
        $service = new ClaudeCliService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseJsonOutput');
        $method->setAccessible(true);

        $jsonOutput = json_encode([
            'result' => 'Response',
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
        $this->assertSame(24100, $result['input_tokens']);
        $this->assertSame(50, $result['output_tokens']);
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
}
