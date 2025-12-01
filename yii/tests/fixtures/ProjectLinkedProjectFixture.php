<?php

namespace tests\fixtures;

use app\models\ProjectLinkedProject;
use yii\test\ActiveFixture;

class ProjectLinkedProjectFixture extends ActiveFixture
{
    public $modelClass = ProjectLinkedProject::class;
    public $dataFile = '@tests/fixtures/data/project_linked_projects.php';
}
