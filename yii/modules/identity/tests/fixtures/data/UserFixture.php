<?php /** @noinspection PhpUnused */

namespace identity\tests\fixtures\data;

use yii\test\ActiveFixture;

class UserFixture extends ActiveFixture
{
    public $modelClass = 'app\modules\identity\models\User';
    public $dataFile = '@tests/fixtures/data/user.php';
}