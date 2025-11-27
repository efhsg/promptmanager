<?php /** @noinspection PhpUnused */

namespace tests\fixtures;

use yii\test\ActiveFixture;

class UserPreferenceFixture extends ActiveFixture
{
    public $modelClass = 'app\models\UserPreference';
    public $dataFile = '@tests/fixtures/data/user_preference.php';
}
