<?php /** @noinspection PhpUnused */

namespace tests\fixtures;

use yii\test\ActiveFixture;

class UserFixture extends ActiveFixture
{
    public $modelClass = 'app\modules\identity\models\User';
    public $dataFile = '@tests/fixtures/data/user.php';
}