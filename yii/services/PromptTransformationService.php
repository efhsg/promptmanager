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

    public function transformForAIModel(string $prompt): string
    {
        $decoded = html_entity_decode($prompt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(
            ['<pre><code>', '</code></pre>', '<p>', '</p>'],
            ['', '', '', "\n\n"],
            $decoded
        );
        return trim($decoded);
    }

}
