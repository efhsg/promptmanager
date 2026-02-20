<?php

namespace tests\unit\services\ai\providers;

use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiStreamingProviderInterface;
use app\services\ai\providers\CodexCliProvider;
use app\services\PathService;
use Codeception\Test\Unit;
use ReflectionMethod;

class CodexCliProviderTest extends Unit
{
    private const TEST_MODELS = [
        'gpt-5.3-codex' => 'GPT 5.3 Codex',
        'gpt-5.2-codex' => 'GPT 5.2 Codex',
    ];

    private CodexCliProvider $provider;

    protected function _before(): void
    {
        $pathService = new PathService([]);
        $this->provider = new CodexCliProvider($pathService, self::TEST_MODELS);
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

    public function testGetConfigSchemaReturnsReasoningField(): void
    {
        $schema = $this->provider->getConfigSchema();

        verify($schema)->arrayHasKey('reasoning');
        verify($schema['reasoning']['type'])->equals('select');
        verify($schema['reasoning']['options'])->arrayHasKey('low');
        verify($schema['reasoning']['options'])->arrayHasKey('medium');
        verify($schema['reasoning']['options'])->arrayHasKey('high');
        verify($schema['reasoning']['options'])->arrayHasKey('extra_high');
    }

    // ── Models & permission modes ─────────────────────────────

    public function testGetSupportedModelsReturnsInjectedModels(): void
    {
        verify($this->provider->getSupportedModels())->equals(self::TEST_MODELS);
    }

    public function testGetSupportedModelsReturnsEmptyWhenNoneInjected(): void
    {
        $provider = new CodexCliProvider(new PathService([]));

        verify($provider->getSupportedModels())->equals([]);
    }

    public function testGetSupportedPermissionModesReturnsEmpty(): void
    {
        verify($this->provider->getSupportedPermissionModes())->equals([]);
    }

    // ── Config detection ──────────────────────────────────────

    public function testHasConfigIgnoresCodexMd(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/codex.md', '# Codex');

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->false();

        unlink($tmpDir . '/codex.md');
        rmdir($tmpDir);
    }

    public function testHasConfigIgnoresCodexMdUppercase(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/CODEX.md', '# Codex');

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->false();

        unlink($tmpDir . '/CODEX.md');
        rmdir($tmpDir);
    }

    public function testHasConfigDetectsAgentsMd(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/AGENTS.md', '# Agents');

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->true();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->true();

        unlink($tmpDir . '/AGENTS.md');
        rmdir($tmpDir);
    }

    public function testHasConfigIgnoresCodexDir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir . '/.codex', 0o755, true);

        $result = $this->provider->hasConfig($tmpDir);

        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->false();

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

    public function testCheckConfigUsesAgentsLabel(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $result = $this->provider->checkConfig($tmpDir);

        verify($result['configFileName'])->equals('AGENTS.md');
        verify($result['hasConfigFile'])->false();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->false();
        verify($result['pathStatus'])->equals('no_config');

        rmdir($tmpDir);
    }

    public function testCheckConfigReturnsHasConfigPathStatusWhenAgentsExists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/codex_test_' . uniqid();
        mkdir($tmpDir, 0o755, true);
        file_put_contents($tmpDir . '/AGENTS.md', '# Agents');

        $result = $this->provider->checkConfig($tmpDir);

        verify($result['configFileName'])->equals('AGENTS.md');
        verify($result['hasConfigFile'])->true();
        verify($result['hasConfigDir'])->false();
        verify($result['hasAnyConfig'])->true();
        verify($result['pathStatus'])->equals('has_config');

        unlink($tmpDir . '/AGENTS.md');
        rmdir($tmpDir);
    }

    // ── Slash command support ────────────────────────────────

    public function testSupportsSlashCommandsReturnsFalse(): void
    {
        verify($this->provider->supportsSlashCommands())->false();
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
        verify($result)->equals(['text' => '', 'session_id' => null, 'metadata' => [], 'error' => null]);

        $result = $this->provider->parseStreamResult('');
        verify($result)->equals(['text' => '', 'session_id' => null, 'metadata' => [], 'error' => null]);
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

    public function testParseStreamResultExtractsFlatTextFromItemCompleted(): void
    {
        $streamLog = '{"type":"thread.started","thread_id":"019c71d9-5911-7353-82ae-77c8f858a5e6"}' . "\n"
            . '{"type":"turn.started"}' . "\n"
            . '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"Hello."}}' . "\n"
            . '{"type":"turn.completed","usage":{"input_tokens":7833,"cached_input_tokens":6528,"output_tokens":6}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Hello.');
        verify($result['session_id'])->equals('019c71d9-5911-7353-82ae-77c8f858a5e6');
        verify($result['metadata']['input_tokens'])->equals(7833);
        verify($result['metadata']['output_tokens'])->equals(6);
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

    public function testParseStreamResultExtractsErrorFromStreamEvents(): void
    {
        $streamLog = '{"type":"thread.started","thread_id":"019c71e8-2f5d-7620-8ab9-5e65b92e7922"}' . "\n"
            . '{"type":"item.completed","item":{"id":"item_0","type":"error","message":"Model metadata for `codex-mini-latest` not found."}}' . "\n"
            . '{"type":"turn.started"}' . "\n"
            . '{"type":"error","message":"The \'codex-mini-latest\' model is not supported."}' . "\n"
            . '{"type":"turn.failed","error":{"message":"The \'codex-mini-latest\' model is not supported."}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('');
        verify($result['session_id'])->equals('019c71e8-2f5d-7620-8ab9-5e65b92e7922');
        verify($result['error'])->stringContainsString('Model metadata for `codex-mini-latest` not found.');
        verify($result['error'])->stringContainsString('not supported');
    }

    public function testParseStreamResultReturnsNullErrorWhenNoErrors(): void
    {
        $streamLog = '{"type":"item.completed","item":{"type":"agent_message","text":"Hello."}}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['error'])->null();
    }

    // ── buildCommand ───────────────────────────────────────────

    public function testBuildCommandBypassesSandboxAndSkipsGitCheck(): void
    {
        $cmd = $this->invokeBuildCommand([], null);

        verify($cmd)->stringContainsString('--dangerously-bypass-approvals-and-sandbox');
        verify($cmd)->stringContainsString('--skip-git-repo-check');
        verify($cmd)->stringNotContainsString('resume');
    }

    public function testBuildCommandIncludesResumeForExistingSession(): void
    {
        $cmd = $this->invokeBuildCommand([], 'session-abc-123');

        verify($cmd)->stringContainsString('resume');
        verify($cmd)->stringContainsString('session-abc-123');
    }

    public function testBuildCommandIncludesModelOption(): void
    {
        $cmd = $this->invokeBuildCommand(['model' => 'gpt-5.3-codex'], null);

        verify($cmd)->stringContainsString("--model 'gpt-5.3-codex'");
    }

    public function testBuildCommandIncludesReasoningOption(): void
    {
        $cmd = $this->invokeBuildCommand(['reasoning' => 'extra_high'], null);

        verify($cmd)->stringContainsString('-c');
        verify($cmd)->stringContainsString('reasoning=extra-high');
    }

    // ── execute ──────────────────────────────────────────────────

    public function testExecuteReturnsErrorForNonExistentDirectory(): void
    {
        $result = $this->provider->execute('test', '/nonexistent/path/for/codex/test');

        verify($result['success'])->false();
        verify($result['exitCode'])->equals(1);
        verify($result['error'])->stringContainsString('does not exist');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function invokeBuildCommand(array $options, ?string $sessionId): string
    {
        $method = new ReflectionMethod(CodexCliProvider::class, 'buildCommand');

        return $method->invoke($this->provider, $options, $sessionId);
    }
}
