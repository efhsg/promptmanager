<?php

namespace tests\fixtures;

use app\models\Project;
use yii\test\ActiveFixture;

class ProjectFixture extends ActiveFixture
{
    public $modelClass = Project::class;
    public $dataFile = '@tests/fixtures/data/projects.php';
}
