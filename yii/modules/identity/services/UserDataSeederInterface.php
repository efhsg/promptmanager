<?php

namespace app\modules\identity\services;

interface UserDataSeederInterface
{
    public function seed(int $userId): void;
}
