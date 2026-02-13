<?php

namespace tests\fixtures;

use app\models\Note;
use yii\test\ActiveFixture;

class NoteFixture extends ActiveFixture
{
    public $modelClass = Note::class;
    public $dataFile = '@tests/fixtures/data/notes.php';
    public $depends = [
        UserFixture::class,
        ProjectFixture::class,
    ];
}
