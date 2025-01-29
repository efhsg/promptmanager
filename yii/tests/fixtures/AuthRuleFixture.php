<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AuthRuleFixture extends ActiveFixture
{
    public $tableName = '{{%auth_rule}}';
    public $modelClass = 'yii\rbac\Rule';
    public $dataFile = '@tests/fixtures/data/auth_rule.php';
}
