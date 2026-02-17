<?php

namespace app\services;

/**
 * Relays NDJSON stream events from a file to a callback.
 *
 * The worker appends lines to the file; this service reads from
 * a given byte offset and passes each non-empty line to the callback.
 */
class AiStreamRelayService
{
    /**
     * Reads stream events from a file starting at the given byte offset.
     *
     * @param string $filePath Path to the NDJSON stream file
     * @param int $offset Byte offset to start reading from
     * @param callable(string): void $onLine Callback for each line
     * @param callable(): bool $isRunning Returns true if the run is still active
     * @param int $maxWaitSeconds Maximum time to wait for new data before returning
     * @return int New byte offset after reading
     */
    public function relay(
        string $filePath,
        int $offset,
        callable $onLine,
        callable $isRunning,
        int $maxWaitSeconds = 60
    ): int {
        clearstatcache(true, $filePath);
        if (!file_exists($filePath)) {
            return $offset;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return $offset;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $startTime = time();
        $done = false;

        while (!$done && (time() - $startTime) < $maxWaitSeconds) {
            $newData = false;

            while (($line = fgets($handle)) !== false) {
                $newData = true;
                $line = trim($line);

                if ($line === '[DONE]') {
                    $done = true;
                    break;
                }

                if ($line !== '') {
                    $onLine($line);
                }
            }

            // Clear EOF flag so fgets() can see newly appended data
            fseek($handle, 0, SEEK_CUR);

            if ($done) {
                break;
            }

            // Check if the run is still active
            if (!$isRunning()) {
                // Read any remaining data
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '[DONE]') {
                        break;
                    }
                    if ($line !== '') {
                        $onLine($line);
                    }
                }
                break;
            }

            // If no new data, wait briefly before retrying
            if (!$newData) {
                usleep(250000); // 250ms
            }
        }

        $newOffset = (int) ftell($handle);
        fclose($handle);

        return $newOffset;
    }
}
