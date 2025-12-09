<?php

namespace tests\unit\commands;

use app\commands\FieldOptionController;
use app\models\FieldOption;
use Codeception\Test\Unit;
use tests\fixtures\FieldFixture;
use tests\fixtures\FieldOptionFixture;
use Yii;
use yii\console\ExitCode;

class FieldOptionControllerTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'fields' => FieldFixture::class,
            'fieldOptions' => FieldOptionFixture::class,
        ];
    }

    public function testConvertToQuillNormalizesValues(): void
    {
        $controller = new FieldOptionController('field-option', Yii::$app);

        $plainOption = FieldOption::find()->where(['value' => 'class'])->one();
        $this->assertNotNull($plainOption);

        $arrayOpsOption = new FieldOption([
            'field_id' => $plainOption->field_id,
            'value' => '[{"insert":"Wrapped option"}]',
            'selected_by_default' => false,
        ]);
        $arrayOpsOption->save(false);

        $existingDeltaValue = '{"ops":[{"insert":"Already formatted\n"}]}';
        $existingDeltaOption = new FieldOption([
            'field_id' => $plainOption->field_id,
            'value' => $existingDeltaValue,
            'selected_by_default' => false,
        ]);
        $existingDeltaOption->save(false);

        $exitCode = $controller->actionConvertToQuill();

        $plainOption->refresh();
        $arrayOpsOption->refresh();
        $existingDeltaOption->refresh();

        $this->assertSame(ExitCode::OK, $exitCode);
        $this->assertSame('{"ops":[{"insert":"class\n"}]}', $plainOption->value);
        $this->assertSame('{"ops":[{"insert":"Wrapped option"}]}', $arrayOpsOption->value);
        $this->assertSame($existingDeltaValue, $existingDeltaOption->value);
    }
}
