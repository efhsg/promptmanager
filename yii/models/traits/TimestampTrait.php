<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace app\models\traits;

trait TimestampTrait
{
    /**
     * Optional override for the current timestamp, useful in tests to avoid sleep().
     */
    protected static ?string $timestampOverride = null;

    /**
     * Set a temporary timestamp override.
     */
    public static function setTimestampOverride(?string $timestamp): void
    {
        static::$timestampOverride = $timestamp;
    }

    /**
     * Set timestamps for the model.
     *
     * @param bool $insert Whether this is a new record
     * @return void
     */
    protected function handleTimestamps(bool $insert): void
    {
        $time = static::$timestampOverride ?? date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $time;
        }
        $this->updated_at = $time;
    }
}
