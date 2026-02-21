<?php

namespace tests\fixtures;

use app\models\ProjectWorktree;
use yii\test\ActiveFixture;

class ProjectWorktreeFixture extends ActiveFixture
{
    public $modelClass = ProjectWorktree::class;
    public $dataFile = '@tests/fixtures/data/project_worktrees.php';
    public $depends = [
        UserFixture::class,
        ProjectFixture::class,
    ];
}
