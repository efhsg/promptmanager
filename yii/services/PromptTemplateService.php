<?php

namespace app\services;

use app\models\PromptTemplate;
use yii\db\Exception;

class PromptTemplateService
{
    /**
     * @throws Exception
     */
    public function saveModel(PromptTemplate $model, array $postData): bool
    {
        return $model->load($postData) && $model->save();
    }
}
