<?php


namespace tests\fixtures;

use app\models\PromptInstance;
use yii\test\ActiveFixture;

class PromptInstanceFixture extends ActiveFixture
{
    public $modelClass = PromptInstance::class;
    public $dataFile = '@tests/fixtures/data/prompt_instance.php';
}
