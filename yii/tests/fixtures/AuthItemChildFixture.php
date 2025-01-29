<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AuthItemChildFixture extends ActiveFixture
{
    public $tableName = '{{%auth_item_child}}';
    public $modelClass = 'yii\rbac\ItemChild';
    public $dataFile = '@tests/fixtures/data/auth_item_child.php';
}
