<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AuthItemFixture extends ActiveFixture
{
    public $tableName = '{{%auth_item}}';
    public $modelClass = 'yii\rbac\Item';
    public $dataFile = '@tests/fixtures/data/auth_item.php';
}
