<?php

namespace app\commands;

use app\models\FieldOption;
use JsonException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception;

/**
 * Console command to convert FieldOption values to Quill delta format.
 *
 * Iterates FieldOption records and converts plain text or legacy delta forms
 * into a standardized Quill delta JSON stored in the `value` attribute.
 */
class FieldOptionController extends Controller
{
    /**
     * @throws Exception
     */
    public function actionConvertToQuill(): int
    {
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach (FieldOption::find()->each() as $option) {
            /** @var FieldOption $option */
            $newValue = $this->convertToDelta($option->value);

            if ($newValue === $option->value) {
                $skipped++;
                continue;
            }

            $option->value = $newValue;
            if ($option->save(false)) {
                $converted++;
                continue;
            }

            $failed++;
            $this->stderr("Failed to convert field_option ID $option->id\n");
        }

        $this->stdout("Converted: $converted, skipped: $skipped, failed: $failed\n");

        return $failed === 0 ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function convertToDelta(string $value): string
    {
        $ops = $this->decodeOps($value);
        if ($ops !== null) {
            return $this->encodeOps($ops);
        }

        return $this->encodeOps([['insert' => $this->normalizePlainText($value)]]);
    }

    private function decodeOps(string $value): ?array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (isset($decoded['ops']) && is_array($decoded['ops'])) {
            return $decoded['ops'];
        }

        if ($this->isOpsArray($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function isOpsArray(mixed $value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        if (array_keys($value) !== range(0, count($value) - 1)) {
            return false;
        }

        foreach ($value as $op) {
            if (!is_array($op) || !array_key_exists('insert', $op)) {
                return false;
            }
        }

        return true;
    }

    private function encodeOps(array $ops): string
    {
        return json_encode(['ops' => $ops], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function normalizePlainText(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        if ($normalized === '') {
            return "\n";
        }

        return str_ends_with($normalized, "\n") ? $normalized : $normalized . "\n";
    }
}
