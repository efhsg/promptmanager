<?php

namespace common\enums;

/**
 * Color scheme options for the navbar background.
 * Set per project (persistent) or per browser tab (ephemeral sessionStorage override).
 */
enum ColorScheme: string
{
    case Default = 'default';
    case Green = 'green';
    case Red = 'red';
    case Purple = 'purple';
    case Orange = 'orange';
    case Dark = 'dark';
    case Teal = 'teal';

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
            self::Default => 'Spacelab (Blue)',
            self::Green => 'Green',
            self::Red => 'Red',
            self::Purple => 'Purple',
            self::Orange => 'Orange',
            self::Dark => 'Dark',
            self::Teal => 'Teal',
        };
    }

    public function primaryColor(): string
    {
        return match ($this) {
            self::Default => '#3b6dbc',
            self::Green => '#2e8b57',
            self::Red => '#c0392b',
            self::Purple => '#6f42c1',
            self::Orange => '#e67e22',
            self::Dark => '#343a40',
            self::Teal => '#20c997',
        };
    }

    public function primaryHoverColor(): string
    {
        return match ($this) {
            self::Default => '#325d9e',
            self::Green => '#267347',
            self::Red => '#a93226',
            self::Purple => '#5a32a3',
            self::Orange => '#cf711c',
            self::Dark => '#23272b',
            self::Teal => '#1aa87c',
        };
    }
}
