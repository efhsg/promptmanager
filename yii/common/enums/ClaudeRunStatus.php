<?php

namespace common\enums;

enum ClaudeRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_map(static fn(self $status): string => $status->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::COMPLETED => 'bg-success',
            self::FAILED => 'bg-danger',
            self::RUNNING => 'bg-warning text-dark',
            self::CANCELLED => 'bg-secondary',
            self::PENDING => 'bg-info',
        };
    }

    /**
     * @return string[] Statuses that indicate an active (non-terminal) run.
     */
    public static function activeValues(): array
    {
        return [self::PENDING->value, self::RUNNING->value];
    }

    /**
     * @return string[] Statuses that indicate a finished run.
     */
    public static function terminalValues(): array
    {
        return [self::COMPLETED->value, self::FAILED->value, self::CANCELLED->value];
    }
}
