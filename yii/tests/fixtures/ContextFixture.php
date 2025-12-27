<?php

namespace tests\fixtures;

use app\models\Context;
use yii\test\ActiveFixture;

class ContextFixture extends ActiveFixture
{
    public $modelClass = Context::class;
    public $dataFile = '@tests/fixtures/data/contexts.php';
}
