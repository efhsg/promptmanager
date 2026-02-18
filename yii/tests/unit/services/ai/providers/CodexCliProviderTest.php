<?php

namespace tests\unit\services\ai\providers;

use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiStreamingProviderInterface;
use app\services\ai\providers\CodexCliProvider;
use app\services\PathService;
use Codeception\Test\Unit;

class CodexCliProviderTest extends Unit
{
    private CodexCliProvider $provider;

    protected function _before(): void
    {
        $pathService = new PathService([]);
        $this->provider = new CodexCliProvider($pathService);
    }

    // ── Identity ──────────────────────────────────────────────

    public function testGetNameReturnsCodex(): void
    {
        verify($this->provider->getName())->equals('Codex');
    }

    public function testGetIdentifierReturnsCodex(): void
    {
        verify($this->provider->getIdentifier())->equals('codex');
    }

    // ── Interface compliance ──────────────────────────────────

    public function testImplementsAiProviderInterface(): void
    {
        $this->assertInstanceOf(AiProviderInterface::class, $this->provider);
    }

    public function testImplementsAiStreamingProviderInterface(): void
    {
        $this->assertInstanceOf(AiStreamingProviderInterface::class, $this->provider);
    }

    public function testImplementsAiConfigProviderInterface(): void
    {
        $this->assertInstanceOf(AiConfigProviderInterface::class, $this->provider);
    }

    // ── Config schema ─────────────────────────────────────────

    public function testGetConfigSchemaReturnsApprovalMode(): void
    {
        $schema = $this->provider->getConfigSchema();

        verify($schema)->arrayHasKey('approvalMode');
        verify($schema['approvalMode']['type'])->equals('select');
        verify($schema['approvalMode']['label'])->equals('Approval Mode');
        verify($schema['approvalMode']['options'])->arrayHasKey('suggest');
        verify($schema['approvalMode']['options'])->arrayHasKey('auto-edit');
        verify($schema['approvalMode']['options'])->arrayHasKey('full-auto');
    }

    // ── Models & permission modes ─────────────────────────────

    public function testGetSupportedModelsReturnsCodexModels(): void
    {
        $models = $this->provider->getSupportedModels();

        verify($models)->arrayHasKey('codex-mini-latest');
        verify($models)->arrayHasKey('o4-mini');
        verify($models)->arrayHasKey('o3');
    }

    public function testGetSupportedPermissionModesReturnsEmpty(): void
    {
        verify($this->provider->getSupportedPermissionModes())->equals([]);
    }

    // ── Config detection ──────────────────────────────────────

    public function testHasConfigDetectsCodexMd(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/codex.md', '# Codex');

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->true();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->true();

        unlink($tmpDir . '/codex.md');
        rmdir($tmpDir);
    }

    public function testHasConfigDetectsCodexDir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir . '/.codex', 0o755, true);

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->true();
        verify($result['hasAnyConfig'])->true();

        rmdir($tmpDir . '/.codex');
        rmdir($tmpDir);
    }

    public function testHasConfigReturnsFalseWhenNoConfig(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->false();

        rmdir($tmpDir);
    }

    // ── Commands ──────────────────────────────────────────────

    public function testLoadCommandsReturnsEmpty(): void
    {
        verify($this->provider->loadCommands('/some/path'))->equals([]);
    }

    // ── parseStreamResult ─────────────────────────────────────

    public function testParseStreamResultHandlesEmptyStream(): void
    {
        $result = $this->provider->parseStreamResult(null);
        verify($result)->equals(['text' => '', 'session_id' => null, 'metadata' => []]);

        $result = $this->provider->parseStreamResult('');
        verify($result)->equals(['text' => '', 'session_id' => null, 'metadata' => []]);
    }

    public function testParseStreamResultExtractsSessionIdFromThreadStarted(): void
    {
        $streamLog = '{"type":"thread.started","thread_id":"thread-abc123"}' . "\n"
            . '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Hello"}]}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['session_id'])->equals('thread-abc123');
    }

    public function testParseStreamResultExtractsTextFromItemCompleted(): void
    {
        $streamLog = '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"First part"}]}}' . "\n"
            . '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Second part"}]}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals("First part\nSecond part");
    }

    public function testParseStreamResultExtractsUsageFromTurnCompleted(): void
    {
        $streamLog = '{"type":"turn.completed","usage":{"input_tokens":100,"output_tokens":50,"total_tokens":150}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['metadata']['input_tokens'])->equals(100);
        verify($result['metadata']['output_tokens'])->equals(50);
        verify($result['metadata']['total_tokens'])->equals(150);
    }

    public function testParseStreamResultIgnoresUnknownEventTypes(): void
    {
        $streamLog = '{"type":"unknown_event","data":"ignored"}' . "\n"
            . '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Result"}]}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Result');
    }

    public function testParseStreamResultIgnoresNonAgentMessageItems(): void
    {
        $streamLog = '{"type":"item.completed","item":{"type":"tool_use","content":[{"type":"text","text":"ignored"}]}}' . "\n"
            . '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Visible"}]}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Visible');
    }

    public function testParseStreamResultSkipsDoneMarker(): void
    {
        $streamLog = '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Done"}]}}' . "\n"
            . '[DONE]';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Done');
    }

    public function testParseStreamResultSkipsInvalidJson(): void
    {
        $streamLog = 'not valid json' . "\n"
            . '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Valid"}]}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Valid');
    }

    // ── buildCommand (tested via execute with non-existent dir) ──

    public function testExecuteReturnsErrorForNonExistentDirectory(): void
    {
        $result = $this->provider->execute('test', '/nonexistent/path/for/codex/test');

        verify($result['success'])->false();
        verify($result['exitCode'])->equals(1);
        verify($result['error'])->stringContainsString('does not exist');
    }
}
