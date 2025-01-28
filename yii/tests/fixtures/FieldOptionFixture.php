<?php

namespace tests\fixtures;

use app\models\FieldOption;
use yii\test\ActiveFixture;

class FieldOptionFixture extends ActiveFixture
{
    public $modelClass = FieldOption::class;
    public $dataFile = '@tests/fixtures/data/field_options.php';
}
