<?php

namespace app\services;

use yii\base\Component;

class CodeFormatterService extends Component
{
    public function detectCode(string $value): bool
    {
        return stripos($value, '<?php') !== false;
    }

    public function wrapCode(string $value, bool $encode = true): string
    {
        return '<pre><code>' . ($encode ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value) . '</code></pre>';
    }
}
