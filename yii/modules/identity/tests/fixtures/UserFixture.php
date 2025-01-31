<?php /** @noinspection PhpUnused */

namespace identity\tests\fixtures;

use yii\test\ActiveFixture;

class UserFixture extends ActiveFixture
{
    public $modelClass = 'app\modules\identity\models\User';
    public $dataFile = '@modules/identity/tests/fixtures/data/user.php';
}