<?php

namespace tests\unit\services;

use app\services\ClaudeStreamRelayService;
use Codeception\Test\Unit;

class ClaudeStreamRelayServiceTest extends Unit
{
    private string $tempDir;

    protected function _before(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/claude_relay_test_' . uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    protected function _after(): void
    {
        // Cleanup temp files
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testRelayReadsFromOffset(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        $lines = [
            '{"type":"system","subtype":"init"}',
            '{"type":"assistant","message":"hello"}',
            '{"type":"result","result":"done"}',
            '[DONE]',
        ];
        file_put_contents($file, implode("\n", $lines) . "\n");

        $received = [];
        $service = new ClaudeStreamRelayService();
        $newOffset = $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return false; }, // Not running — read all and stop
            1
        );

        verify(count($received))->equals(3); // 3 lines (not [DONE])
        verify($received[0])->stringContainsString('system');
        verify($received[1])->stringContainsString('assistant');
        verify($received[2])->stringContainsString('result');
        verify($newOffset)->greaterThan(0);
    }

    public function testRelaySkipsEmptyLines(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        file_put_contents($file, "\n\n{\"type\":\"result\"}\n\n[DONE]\n");

        $received = [];
        $service = new ClaudeStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return false; },
            1
        );

        verify(count($received))->equals(1);
        verify($received[0])->stringContainsString('result');
    }

    public function testRelayHandlesMissingFile(): void
    {
        $service = new ClaudeStreamRelayService();
        $newOffset = $service->relay(
            '/nonexistent/file.ndjson',
            0,
            function (string $line) {
                $this->fail('Should not receive any data');
            },
            function () { return false; },
            1
        );

        verify($newOffset)->equals(0);
    }

    public function testRelayReturnsNewOffset(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        $content = "{\"type\":\"line1\"}\n{\"type\":\"line2\"}\n[DONE]\n";
        file_put_contents($file, $content);

        $received = [];
        $service = new ClaudeStreamRelayService();
        $offset = $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return false; },
            1
        );

        verify($offset)->equals(strlen($content));
        verify(count($received))->equals(2);
    }

    public function testRelayReadsFromByteOffset(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        $line1 = "{\"type\":\"line1\"}\n";
        $line2 = "{\"type\":\"line2\"}\n";
        $done = "[DONE]\n";
        file_put_contents($file, $line1 . $line2 . $done);

        $received = [];
        $service = new ClaudeStreamRelayService();
        $service->relay(
            $file,
            strlen($line1), // Start after first line
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return false; },
            1
        );

        verify(count($received))->equals(1);
        verify($received[0])->stringContainsString('line2');
    }

    public function testRelayStopsWhenRunNotActive(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        file_put_contents($file, "{\"type\":\"line1\"}\n");

        $callCount = 0;
        $service = new ClaudeStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$callCount) {
                $callCount++;
            },
            function () { return false; }, // Not running
            2
        );

        verify($callCount)->equals(1);
    }
}
