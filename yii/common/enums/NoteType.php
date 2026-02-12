<?php

namespace common\enums;

enum NoteType: string
{
    case NOTE = 'note';
    case SUMMATION = 'summation';
    case IMPORT = 'import';

    private const LEGACY_MAP = [
        'response' => 'summation',
    ];

    /**
     * Resolves a type string to a NoteType, including legacy values.
     */
    public static function resolve(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(self::LEGACY_MAP[$value] ?? $value);
    }

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
            self::NOTE => 'Note',
            self::SUMMATION => 'Summation',
            self::IMPORT => 'Import',
        };
    }
}
