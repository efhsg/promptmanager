<?php

namespace app\services;

use yii\base\Component;

class CodeFormatterService extends Component
{
    /**
     * Detects if the given value contains PHP code.
     *
     * @param string $value
     * @return bool
     */
    public function detectCode(string $value): bool
    {
        return stripos($value, '<?php') !== false;
    }

    /**
     * Wraps the given code value in pre and code tags.
     *
     * @param string $value
     * @return string
     */
    public function wrapCode(string $value): string
    {
        return '<pre><code>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }
}
