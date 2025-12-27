<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace app\models\traits;

trait TimestampTrait
{
    /**
     * Optional override for the current timestamp, useful in tests to avoid sleep().
     */
    protected static ?int $timestampOverride = null;

    /**
     * Set a temporary timestamp override.
     */
    public static function setTimestampOverride(?int $timestamp): void
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
        $time = static::$timestampOverride ?? time();
        if ($insert) {
            $this->created_at = $time;
        }
        $this->updated_at = $time;
    }
}
