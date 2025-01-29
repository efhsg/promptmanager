<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AuthAssignmentFixture extends ActiveFixture
{
    public $tableName = '{{%auth_assignment}}';
    public $modelClass = 'yii\rbac\Assignment';
    public $dataFile = '@tests/fixtures/data/auth_assignment.php';
}
