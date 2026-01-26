<?php

namespace tests\unit\services;

use app\services\YouTubeTranscriptService;
use Codeception\Test\Unit;
use RuntimeException;

class YouTubeTranscriptServiceTest extends Unit
{
    private YouTubeTranscriptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YouTubeTranscriptService(
            pythonPath: '/usr/bin/python3',
            scriptPath: '/opt/ytx/ytx.py'
        );
    }

    public function testExtractVideoIdFromStandardUrl(): void
    {
        $videoId = $this->service->extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdFromShortUrl(): void
    {
        $videoId = $this->service->extractVideoId('https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdFromEmbedUrl(): void
    {
        $videoId = $this->service->extractVideoId('https://www.youtube.com/embed/dQw4w9WgXcQ');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdFromVUrl(): void
    {
        $videoId = $this->service->extractVideoId('https://www.youtube.com/v/dQw4w9WgXcQ');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdFromRawId(): void
    {
        $videoId = $this->service->extractVideoId('dQw4w9WgXcQ');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdFromUrlWithQueryParams(): void
    {
        $videoId = $this->service->extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120');

        $this->assertSame('dQw4w9WgXcQ', $videoId);
    }

    public function testExtractVideoIdThrowsExceptionForInvalidInput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract video ID from:');

        $this->service->extractVideoId('not-a-valid-youtube-url');
    }

    public function testExtractVideoIdThrowsExceptionForEmptyString(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->extractVideoId('');
    }

    public function testBuildMarkdownContentIncludesTitle(): void
    {
        $transcriptData = [
            'title' => 'Test Video Title',
            'channel' => 'Test Channel',
            'url' => 'https://youtube.com/watch?v=abc123',
            'transcript' => 'Hello world',
        ];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('# Test Video Title', $markdown);
    }

    public function testBuildMarkdownContentIncludesChannelAndUrl(): void
    {
        $transcriptData = [
            'title' => 'Test Video',
            'channel' => 'My Channel',
            'url' => 'https://youtube.com/watch?v=xyz789',
            'transcript' => 'Content here',
        ];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('**Channel:** My Channel', $markdown);
        $this->assertStringContainsString('**URL:** https://youtube.com/watch?v=xyz789', $markdown);
    }

    public function testBuildMarkdownContentIncludesTools(): void
    {
        $transcriptData = [
            'title' => 'Tutorial',
            'channel' => 'Tech Channel',
            'tools' => ['VS Code', 'Docker', 'Git'],
            'transcript' => 'Content',
        ];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('## Tools & Products Mentioned', $markdown);
        $this->assertStringContainsString('- VS Code', $markdown);
        $this->assertStringContainsString('- Docker', $markdown);
        $this->assertStringContainsString('- Git', $markdown);
    }

    public function testBuildMarkdownContentIncludesSteps(): void
    {
        $transcriptData = [
            'title' => 'How To',
            'channel' => 'Tutorial Channel',
            'steps' => ['install dependencies', 'configure settings', 'run the app'],
            'transcript' => 'Content',
        ];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('## Key Steps & Actions', $markdown);
        $this->assertStringContainsString('1. Install dependencies', $markdown);
        $this->assertStringContainsString('2. Configure settings', $markdown);
        $this->assertStringContainsString('3. Run the app', $markdown);
    }

    public function testBuildMarkdownContentIncludesTranscript(): void
    {
        $transcriptData = [
            'title' => 'Video',
            'channel' => 'Channel',
            'transcript' => 'This is the full transcript text.',
        ];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('## Transcript', $markdown);
        $this->assertStringContainsString('This is the full transcript text.', $markdown);
    }

    public function testBuildMarkdownContentHandlesMissingOptionalFields(): void
    {
        $transcriptData = [];

        $markdown = $this->service->buildMarkdownContent($transcriptData);

        $this->assertStringContainsString('# Unknown Title', $markdown);
        $this->assertStringContainsString('**Channel:** Unknown Channel', $markdown);
        $this->assertStringNotContainsString('## Tools & Products Mentioned', $markdown);
        $this->assertStringNotContainsString('## Key Steps & Actions', $markdown);
    }

    public function testGetTitleReturnsTitle(): void
    {
        $transcriptData = ['title' => 'My Video Title'];

        $title = $this->service->getTitle($transcriptData);

        $this->assertSame('My Video Title', $title);
    }

    public function testGetTitleReturnsDefaultWhenMissing(): void
    {
        $transcriptData = [];

        $title = $this->service->getTitle($transcriptData);

        $this->assertSame('YouTube Transcript', $title);
    }

    public function testGetTitleTruncatesLongTitles(): void
    {
        $longTitle = str_repeat('A', 300);
        $transcriptData = ['title' => $longTitle];

        $title = $this->service->getTitle($transcriptData, 255);

        $this->assertSame(255, mb_strlen($title));
        $this->assertStringEndsWith('...', $title);
    }

    public function testGetTitleWithCustomMaxLength(): void
    {
        $transcriptData = ['title' => 'This is a somewhat long title'];

        $title = $this->service->getTitle($transcriptData, 20);

        $this->assertSame(20, mb_strlen($title));
        $this->assertSame('This is a somewha...', $title);
    }

    public function testGetTitleDoesNotTruncateShortTitles(): void
    {
        $transcriptData = ['title' => 'Short'];

        $title = $this->service->getTitle($transcriptData, 255);

        $this->assertSame('Short', $title);
    }
}
