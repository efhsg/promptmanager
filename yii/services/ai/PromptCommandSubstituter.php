<?php

namespace app\services\ai;

/**
 * Replaces slash commands in a prompt string with the
 * inlined contents of the corresponding command file.
 */
class PromptCommandSubstituter
{
    /**
     * Substitutes known slash commands in the prompt with their inlined content.
     *
     * @param string $prompt The original prompt text
     * @param array<string, string> $commandContents Command name => file content
     * @return string The prompt with substituted commands
     */
    public function substitute(string $prompt, array $commandContents): string
    {
        if ($commandContents === [] || trim($prompt) === '') {
            return $prompt;
        }

        $escaped = array_map(
            'preg_quote',
            array_keys($commandContents),
            array_fill(0, count($commandContents), '/')
        );
        $names = implode('|', $escaped);
        $pattern = '/(^|\s)\/(' . $names . ')(?=\s|$|[.,;:!?)\]\}])/m';

        return preg_replace_callback($pattern, function (array $matches) use ($commandContents) {
            $name = $matches[2];
            return $matches[1] . $this->prepareContent($commandContents[$name]);
        }, $prompt);
    }

    /**
     * Strips YAML frontmatter and the $ARGUMENTS placeholder from command file content.
     */
    private function prepareContent(string $content): string
    {
        $content = preg_replace('/\A---\h*\r?\n.*?\r?\n---\h*(?:\r?\n)?/s', '', $content);
        $content = str_replace('$ARGUMENTS', '', $content);

        return trim($content);
    }
}
