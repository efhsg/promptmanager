<?php

namespace tests\unit\services\ai;

use app\services\ai\PromptCommandSubstituter;
use Codeception\Test\Unit;

class PromptCommandSubstituterTest extends Unit
{
    private PromptCommandSubstituter $substituter;

    private const COMMANDS = [
        'onboard' => "---\ndescription: Quick start guide\n---\n\nWelcome to the project!",
        'review-changes' => "---\ndescription: Review recent changes\n---\n\nReview all recent code changes.",
    ];

    protected function _before(): void
    {
        $this->substituter = new PromptCommandSubstituter();
    }

    public function testSubstitutesSingleCommand(): void
    {
        $result = $this->substituter->substitute('Please /onboard', self::COMMANDS);

        verify($result)->equals('Please Welcome to the project!');
    }

    public function testSubstitutesMultipleCommands(): void
    {
        $result = $this->substituter->substitute(
            '/onboard then /review-changes',
            self::COMMANDS
        );

        verify($result)->equals(
            'Welcome to the project! then Review all recent code changes.'
        );
    }

    public function testIgnoresUnknownCommands(): void
    {
        $result = $this->substituter->substitute(
            'Run /unknown and /onboard',
            self::COMMANDS
        );

        verify($result)->equals(
            'Run /unknown and Welcome to the project!'
        );
    }

    public function testIgnoresPathsWithSlashes(): void
    {
        $result = $this->substituter->substitute(
            'Read /path/to/file and /onboard',
            self::COMMANDS
        );

        verify($result)->equals(
            'Read /path/to/file and Welcome to the project!'
        );
    }

    public function testHandlesCommandAtStartOfPrompt(): void
    {
        $result = $this->substituter->substitute('/onboard now', self::COMMANDS);

        verify($result)->equals('Welcome to the project! now');
    }

    public function testHandlesCommandAtEndOfPrompt(): void
    {
        $result = $this->substituter->substitute('Please /onboard', self::COMMANDS);

        verify($result)->equals('Please Welcome to the project!');
    }

    public function testPreservesPromptWithNoCommands(): void
    {
        $result = $this->substituter->substitute('Just a normal prompt', self::COMMANDS);

        verify($result)->equals('Just a normal prompt');
    }

    public function testEmptyCommandListReturnsOriginal(): void
    {
        $result = $this->substituter->substitute('Please /onboard', []);

        verify($result)->equals('Please /onboard');
    }

    public function testReturnsOriginalWhenPromptIsEmpty(): void
    {
        $result = $this->substituter->substitute('', self::COMMANDS);

        verify($result)->equals('');
    }

    public function testHandlesAdjacentCommands(): void
    {
        $result = $this->substituter->substitute('/onboard /review-changes', self::COMMANDS);

        verify($result)->equals(
            'Welcome to the project! Review all recent code changes.'
        );
    }

    public function testSubstitutesCommandFollowedByPunctuation(): void
    {
        $result = $this->substituter->substitute(
            'Run /onboard, then /review-changes.',
            self::COMMANDS
        );

        verify($result)->equals(
            'Run Welcome to the project!, then Review all recent code changes..'
        );
    }

    public function testStripsFrontmatter(): void
    {
        $commands = [
            'test' => "---\nallowed-tools: Bash\ndescription: Test\n---\n\nActual content here.",
        ];

        $result = $this->substituter->substitute('/test', $commands);

        verify($result)->equals('Actual content here.');
    }

    public function testStripsFrontmatterWithCrLf(): void
    {
        $commands = [
            'test' => "---\r\nallowed-tools: Bash\r\ndescription: Test\r\n---\r\n\r\nActual content here.",
        ];

        $result = $this->substituter->substitute('/test', $commands);

        verify($result)->equals('Actual content here.');
    }

    public function testStripsArgumentsPlaceholder(): void
    {
        $commands = [
            'review' => "# Review\n\nDo the review.\n\n## Task\n\n\$ARGUMENTS",
        ];

        $result = $this->substituter->substitute('/review', $commands);

        verify($result)->equals("# Review\n\nDo the review.\n\n## Task");
    }

    public function testHandlesContentWithoutFrontmatter(): void
    {
        $commands = [
            'simple' => 'Just plain content',
        ];

        $result = $this->substituter->substitute('/simple', $commands);

        verify($result)->equals('Just plain content');
    }
}
