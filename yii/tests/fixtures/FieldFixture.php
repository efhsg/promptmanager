<?php

namespace tests\fixtures;

use app\models\Field;
use yii\test\ActiveFixture;

class FieldFixture extends ActiveFixture
{
    public $modelClass = Field::class;
    public $dataFile = '@tests/fixtures/data/fields.php';
}
