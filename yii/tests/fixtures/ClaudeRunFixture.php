<?php

namespace tests\fixtures;

use app\models\AiRun;
use yii\test\ActiveFixture;

class ClaudeRunFixture extends ActiveFixture
{
    public $modelClass = AiRun::class;
    public $dataFile = '@tests/fixtures/data/claude_runs.php';
    public $depends = [
        UserFixture::class,
        ProjectFixture::class,
    ];
}
