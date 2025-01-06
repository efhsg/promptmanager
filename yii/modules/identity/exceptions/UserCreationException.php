<?php

namespace app\modules\identity\exceptions;

use Throwable;
use yii\base\Exception;

class UserCreationException extends Exception
{
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
