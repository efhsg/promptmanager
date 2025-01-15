<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace app\models\traits;

trait TimestampTrait
{
    /**
     * Set timestamps for the model.
     *
     * @param bool $insert Whether this is a new record
     * @return void
     */
    protected function handleTimestamps(bool $insert): void
    {
        $time = time();
        if ($insert) {
            $this->created_at = $time;
        }
        $this->updated_at = $time;
    }
}
