<?php

namespace app\services;

use yii\base\Component;

class PromptTransformationService extends Component
{
    public function detectCode(string $value): bool
    {
        return stripos($value, '<?php') !== false;
    }

    public function wrapCode(string $value, bool $encode = true): string
    {
        return '<pre><code>' . ($encode ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value) . '</code></pre>';
    }

    public function transformForAIModel(?string $prompt): string
    {
        if ($prompt === null) {
            return '';
        }

        $decoded = html_entity_decode($prompt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $replacements = [
            '<pre><code>' => "```\n",
            '</code></pre>' => "\n```",
            '<p>' => '',
            '</p>' => "\n\n",
            '<strong>' => '**',
            '</strong>' => '**',
            '<em>' => '*',
            '</em>' => '*',
            '<br>' => "\n",
            '<br/>' => "\n",
            '<br />' => "\n",
            '<ol>' => '',
            '</ol>' => "\n",
            '<ul>' => '',
            '</ul>' => "\n",
            '<li>' => '- ',
            '</li>' => "\n"
        ];
        $decoded = strtr($decoded, $replacements);
        $decoded = preg_replace('/<\s*pre\s*>\s*<\s*code\s*>/i', '', $decoded);
        $decoded = preg_replace('/<\s*\/\s*code\s*>\s*<\s*\/\s*pre\s*>/i', '', $decoded);
        $decoded = preg_replace("/\n{3,}/", "\n\n", $decoded);
        return trim($decoded);
    }


}
