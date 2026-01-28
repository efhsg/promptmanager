<?php

namespace common\enums;

enum ClaudePermissionMode: string
{
    case PLAN = 'plan';
    case DONT_ASK = 'dontAsk';
    case BYPASS_PERMISSIONS = 'bypassPermissions';
    case ACCEPT_EDITS = 'acceptEdits';
    case DEFAULT = 'default';

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
            self::PLAN => 'Plan (restricted to planning)',
            self::DONT_ASK => 'Don\'t Ask (fail on permission needed)',
            self::BYPASS_PERMISSIONS => 'Bypass Permissions (auto-approve all)',
            self::ACCEPT_EDITS => 'Accept Edits (auto-accept edits only)',
            self::DEFAULT => 'Default (interactive)',
        };
    }
}
