<?php


namespace app\services;

use app\models\PromptInstance;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\web\NotFoundHttpException;

class PromptInstanceService
{
    /**
     * @throws Exception
     */
    public function saveModel(PromptInstance $model, array $postData): bool
    {
        return $model->load($postData) && $model->save();
    }
    /**
     * Finds the PromptInstance model based on its primary key value and verifies that it belongs
     * to the provided user.
     *
     * @param int $id
     * @param int $userId
     * @return ActiveRecord
     * @throws NotFoundHttpException if the model is not found or not owned by the user.
     */
    public function findModelWithOwner(int $id, int $userId): ActiveRecord
    {
        $model = PromptInstance::find()
            ->joinWith(['template.project'])
            ->where([
                'prompt_instance.id' => $id,
                'project.user_id' => $userId,
            ])
            ->one();
        if ($model === null) {
            throw new NotFoundHttpException('The requested prompt instance does not exist or is not yours.');
        }
        return $model;
    }
}