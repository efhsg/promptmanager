<?php

namespace tests\unit\services;

use app\services\AiStreamRelayService;
use Codeception\Test\Unit;

class AiStreamRelayServiceTest extends Unit
{
    private string $tempDir;

    protected function _before(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/claude_relay_test_' . uniqid();
        mkdir($this->tempDir, 0o775, true);
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
        $service = new AiStreamRelayService();
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
        $service = new AiStreamRelayService();
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
        $service = new AiStreamRelayService();
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
        $service = new AiStreamRelayService();
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
        $service = new AiStreamRelayService();
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
        $service = new AiStreamRelayService();
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

    public function testRelayDrainsRemainingDataWhenRunStops(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        // No [DONE] marker — relay reads all data in the main loop, then
        // hits isRunning() which returns false, triggering the drain path.
        file_put_contents($file, "{\"type\":\"line1\"}\n{\"type\":\"line2\"}\n{\"type\":\"line3\"}\n");

        $received = [];
        $isRunningCalls = 0;
        $service = new AiStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () use (&$isRunningCalls) {
                $isRunningCalls++;
                return false; // Stop immediately
            },
            1
        );

        // All 3 data lines should be received even though isRunning returned false
        verify(count($received))->equals(3);
        verify($received[0])->stringContainsString('line1');
        verify($received[2])->stringContainsString('line3');
        verify($isRunningCalls)->equals(1);
    }

    public function testRelayStopsAtDoneMarkerMidStream(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        // [DONE] appears before the last line — relay should stop at [DONE]
        file_put_contents($file, "{\"type\":\"line1\"}\n[DONE]\n{\"type\":\"line2\"}\n");

        $received = [];
        $service = new AiStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return true; },
            1
        );

        verify(count($received))->equals(1);
        verify($received[0])->stringContainsString('line1');
    }

    public function testRelayPicksUpAppendedData(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        // Start with one line, append more during relay via $isRunning callback
        file_put_contents($file, "{\"type\":\"line1\"}\n");

        $received = [];
        $appendDone = false;
        $service = new AiStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () use ($file, &$appendDone) {
                if (!$appendDone) {
                    // Simulate the worker appending more data
                    file_put_contents($file, "{\"type\":\"line2\"}\n[DONE]\n", FILE_APPEND);
                    $appendDone = true;
                    return true; // Still running
                }
                return true;
            },
            2
        );

        verify(count($received))->equals(2);
        verify($received[0])->stringContainsString('line1');
        verify($received[1])->stringContainsString('line2');
    }

    public function testRelayRespectsMaxWaitTimeout(): void
    {
        $file = $this->tempDir . '/test.ndjson';
        // File exists but has no [DONE] — relay should timeout
        file_put_contents($file, "{\"type\":\"line1\"}\n");

        $startTime = time();
        $received = [];
        $service = new AiStreamRelayService();
        $service->relay(
            $file,
            0,
            function (string $line) use (&$received) {
                $received[] = $line;
            },
            function () { return true; }, // Always running
            1 // 1 second max wait
        );
        $elapsed = time() - $startTime;

        verify(count($received))->equals(1);
        verify($elapsed)->lessThanOrEqual(3); // Should exit within ~1-2s
    }
}
