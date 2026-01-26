<?php

namespace app\services;

use app\services\copyformat\MarkdownParser;
use app\services\copyformat\QuillDeltaWriter;
use RuntimeException;
use Yii;

/**
 * Fetches YouTube transcripts using the external ytx.py tool.
 */
class YouTubeTranscriptService
{
    private MarkdownParser $markdownParser;
    private QuillDeltaWriter $quillDeltaWriter;
    private string $pythonPath;
    private string $scriptPath;

    public function __construct(
        ?MarkdownParser $markdownParser = null,
        ?QuillDeltaWriter $quillDeltaWriter = null,
        ?string $pythonPath = null,
        ?string $scriptPath = null
    ) {
        $this->markdownParser = $markdownParser ?? new MarkdownParser();
        $this->quillDeltaWriter = $quillDeltaWriter ?? new QuillDeltaWriter();
        $this->pythonPath = $pythonPath ?? Yii::$app->params['ytx']['pythonPath'];
        $this->scriptPath = $scriptPath ?? Yii::$app->params['ytx']['scriptPath'];
    }

    /**
     * Extract video ID from URL or raw ID.
     *
     * Supports:
     * - youtube.com/watch?v=VIDEO_ID
     * - youtu.be/VIDEO_ID
     * - youtube.com/embed/VIDEO_ID
     * - youtube.com/v/VIDEO_ID
     * - Raw 11-character video ID
     *
     * @throws RuntimeException if video ID cannot be extracted
     */
    public function extractVideoId(string $urlOrId): string
    {
        $patterns = [
            '/(?:v=|\/v\/|youtu\.be\/|\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/^([a-zA-Z0-9_-]{11})$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $urlOrId, $matches)) {
                return $matches[1];
            }
        }

        throw new RuntimeException('Could not extract video ID from: ' . $urlOrId);
    }

    /**
     * Fetch transcript from YouTube using ytx.py.
     *
     * @return array{video_id: string, title: string, channel: string, url: string, tools: array, steps: array, transcript: string}
     * @throws RuntimeException if fetching fails
     */
    public function fetchTranscript(string $videoIdOrUrl): array
    {
        $videoId = $this->extractVideoId($videoIdOrUrl);

        $command = sprintf(
            '%s %s %s --json -O 2>&1',
            escapeshellcmd($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($videoId)
        );

        $output = shell_exec($command);

        if ($output === null) {
            throw new RuntimeException('Failed to execute ytx.py command.');
        }

        $data = json_decode($output, true);

        if ($data === null) {
            $errorMessage = $this->parseErrorMessage($output);
            throw new RuntimeException($errorMessage);
        }

        return $data;
    }

    /**
     * Build markdown content from transcript data.
     */
    public function buildMarkdownContent(array $transcriptData): string
    {
        $lines = [];

        // Title
        $title = $transcriptData['title'] ?? 'Unknown Title';
        $lines[] = '# ' . $title;
        $lines[] = '';

        // Metadata
        $channel = $transcriptData['channel'] ?? 'Unknown Channel';
        $url = $transcriptData['url'] ?? '';
        $lines[] = '**Channel:** ' . $channel;
        if ($url) {
            $lines[] = '**URL:** ' . $url;
        }
        $lines[] = '';

        // Tools mentioned
        if (!empty($transcriptData['tools'])) {
            $lines[] = '## Tools & Products Mentioned';
            $lines[] = '';
            foreach ($transcriptData['tools'] as $tool) {
                $lines[] = '- ' . $tool;
            }
            $lines[] = '';
        }

        // Key steps
        if (!empty($transcriptData['steps'])) {
            $lines[] = '## Key Steps & Actions';
            $lines[] = '';
            foreach ($transcriptData['steps'] as $i => $step) {
                $lines[] = ($i + 1) . '. ' . ucfirst($step);
            }
            $lines[] = '';
        }

        // Transcript
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Transcript';
        $lines[] = '';
        $lines[] = $transcriptData['transcript'] ?? '';

        return implode("\n", $lines);
    }

    /**
     * Convert transcript data to Quill Delta JSON.
     */
    public function convertToQuillDelta(array $transcriptData): string
    {
        $markdown = $this->buildMarkdownContent($transcriptData);
        $blocks = $this->markdownParser->parse($markdown);
        return $this->quillDeltaWriter->writeFromBlocks($blocks);
    }

    /**
     * Get video title from transcript data, truncated if necessary.
     */
    public function getTitle(array $transcriptData, int $maxLength = 255): string
    {
        $title = $transcriptData['title'] ?? 'YouTube Transcript';
        if (mb_strlen($title) > $maxLength) {
            return mb_substr($title, 0, $maxLength - 3) . '...';
        }
        return $title;
    }

    /**
     * Parse error message from ytx.py output.
     */
    private function parseErrorMessage(string $output): string
    {
        $errorMappings = [
            'Transcripts are disabled' => 'Transcripts are disabled for this video.',
            'Video unavailable' => 'This video is unavailable or does not exist.',
            'No transcripts found' => 'No transcripts were found for this video.',
            'Could not extract video ID' => 'Invalid YouTube video ID or URL.',
        ];

        foreach ($errorMappings as $pattern => $message) {
            if (stripos($output, $pattern) !== false) {
                return $message;
            }
        }

        Yii::error('YouTube transcript fetch failed: ' . $output, 'youtube');
        return 'Failed to fetch transcript. Please check the video ID and try again.';
    }
}
