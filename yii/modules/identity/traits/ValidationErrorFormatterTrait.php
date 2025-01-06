<?php

namespace app\modules\identity\traits;

use yii\base\Model;

trait ValidationErrorFormatterTrait
{
    public function formatValidationErrors(Model $model): string
    {
        $errors = [];
        foreach ($model->errors as $attribute => $attributeErrors) {
            $errors[] = ucfirst($attribute) . ': ' . implode(', ', $attributeErrors);
        }
        return implode('; ', $errors);
    }
}
