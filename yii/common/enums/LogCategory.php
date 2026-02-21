<?php

namespace common\enums;

/**
 * Standardized log categories for Yii::error/warning/info/debug calls.
 * Keeps category strings in one place for consistency and searchability.
 */
enum LogCategory: string
{
    case APPLICATION = 'application';
    case DATABASE = 'database';
    case AI = 'ai';
    case YOUTUBE = 'youtube';
    case IDENTITY = 'identity';
    case WORKTREE = 'worktree';

    public static function values(): array
    {
        return array_map(static fn(self $type): string => $type->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::APPLICATION => 'Application',
            self::DATABASE => 'Database',
            self::AI => 'AI',
            self::YOUTUBE => 'YouTube',
            self::IDENTITY => 'Identity',
            self::WORKTREE => 'Worktree',
        };
    }
}
