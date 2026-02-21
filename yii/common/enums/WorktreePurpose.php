<?php

namespace common\enums;

enum WorktreePurpose: string
{
    case Feature = 'feature';
    case Bugfix = 'bugfix';
    case Refactor = 'refactor';
    case Spike = 'spike';
    case Custom = 'custom';

    public static function values(): array
    {
        return array_map(static fn(self $p): string => $p->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $purpose) {
            $labels[$purpose->value] = $purpose->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Feature',
            self::Bugfix => 'Bugfix',
            self::Refactor => 'Refactor',
            self::Spike => 'Spike',
            self::Custom => 'Custom',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Feature => 'bg-primary',
            self::Bugfix => 'bg-danger',
            self::Refactor => 'bg-info',
            self::Spike => 'bg-secondary',
            self::Custom => 'bg-dark',
        };
    }
}
