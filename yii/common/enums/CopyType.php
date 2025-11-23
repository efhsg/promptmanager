<?php

namespace common\enums;

enum CopyType: string
{
    case MD = 'md';
    case TEXT = 'text';
    case HTML = 'html';
    case QUILL_DELTA = 'quilldelta';
    case LLM_XML = 'llm-xml';

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
            self::MD => 'Markdown',
            self::TEXT => 'Plain Text',
            self::HTML => 'HTML',
            self::QUILL_DELTA => 'Quill Delta JSON',
            self::LLM_XML => 'LLM XML',
        };
    }
}
