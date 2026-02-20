<?php

namespace tests\unit\services\ai\providers;

use app\services\ai\providers\ClaudeCliProvider;
use app\services\PathService;
use Codeception\Test\Unit;

class ClaudeCliProviderTest extends Unit
{
    private ClaudeCliProvider $provider;

    protected function _before(): void
    {
        $this->provider = new ClaudeCliProvider(new PathService([]));
    }

    // ── parseStreamResult: fallback to assistant text ─────────

    public function testParseStreamResultFallsBackToAssistantTextWhenResultEmpty(): void
    {
        $streamLog = '{"type":"system","session_id":"sess-001"}' . "\n"
            . '{"type":"assistant","message":{"content":[{"type":"text","text":"Fallback answer"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":1200,"num_turns":2}' . "\n"
            . '[DONE]';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Fallback answer');
        verify($result['session_id'])->equals('sess-001');
    }

    public function testParseStreamResultReturnsEmptyWhenNoAssistantText(): void
    {
        $streamLog = '{"type":"system","session_id":"sess-002"}' . "\n"
            . '{"type":"result","result":"","duration_ms":500}' . "\n"
            . '[DONE]';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('');
    }

    public function testParseStreamResultSkipsSidechainAssistantMessages(): void
    {
        $streamLog = '{"type":"assistant","isSidechain":true,"message":{"content":[{"type":"text","text":"Sidechain noise"}]}}' . "\n"
            . '{"type":"assistant","message":{"content":[{"type":"text","text":"Main answer"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":800}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Main answer');
    }

    public function testParseStreamResultSkipsToolUseAssistantMessages(): void
    {
        $streamLog = '{"type":"assistant","parent_tool_use_id":"tool-123","message":{"content":[{"type":"text","text":"Tool output"}]}}' . "\n"
            . '{"type":"assistant","message":{"content":[{"type":"text","text":"Real answer"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":600}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Real answer');
    }

    public function testParseStreamResultConcatenatesMultipleTextBlocks(): void
    {
        $streamLog = '{"type":"assistant","message":{"content":[{"type":"text","text":"Part one"},{"type":"text","text":"Part two"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":300}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals("Part one\nPart two");
    }

    public function testParseStreamResultIgnoresNonTextContentBlocks(): void
    {
        $streamLog = '{"type":"assistant","message":{"content":[{"type":"tool_use","id":"t1"},{"type":"text","text":"Visible"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":400}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Visible');
    }

    public function testParseStreamResultPrefersResultTextOverFallback(): void
    {
        $streamLog = '{"type":"assistant","message":{"content":[{"type":"text","text":"Old turn"}]}}' . "\n"
            . '{"type":"result","result":"Canonical answer","duration_ms":500}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Canonical answer');
    }

    public function testParseStreamResultUsesLastMainThreadAssistant(): void
    {
        $streamLog = '{"type":"assistant","message":{"content":[{"type":"text","text":"First turn"}]}}' . "\n"
            . '{"type":"assistant","parent_tool_use_id":"t1","message":{"content":[{"type":"text","text":"Nested"}]}}' . "\n"
            . '{"type":"assistant","message":{"content":[{"type":"text","text":"Final turn"}]}}' . "\n"
            . '{"type":"result","result":"","duration_ms":900}';

        $result = $this->provider->parseStreamResult($streamLog);

        verify($result['text'])->equals('Final turn');
    }
}
