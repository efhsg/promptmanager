<?php


namespace tests\fixtures;

use app\models\PromptTemplate;
use yii\test\ActiveFixture;

class PromptTemplateFixture extends ActiveFixture
{
    public $modelClass = PromptTemplate::class;
    public $dataFile = '@tests/fixtures/data/prompt_template.php';
}
