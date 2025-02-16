<?php


namespace app\services;

use app\models\PromptInstance;
use yii\db\Exception;

class PromptInstanceService
{
    /**
     * @throws Exception
     */
    public function saveModel(PromptInstance $model, array $postData): bool
    {
        return $model->load($postData) && $model->save();
    }
}
