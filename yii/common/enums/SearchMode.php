<?php

namespace common\enums;

enum SearchMode: string
{
    case PHRASE = 'phrase';
    case KEYWORDS = 'keywords';

    public static function values(): array
    {
        return array_map(static fn(self $mode): string => $mode->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $mode) {
            $labels[$mode->value] = $mode->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::PHRASE => 'Exact phrase',
            self::KEYWORDS => 'Any keyword',
        };
    }
}
