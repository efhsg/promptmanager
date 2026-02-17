<?php

namespace app\services;

use common\enums\LogCategory;
use Throwable;
use Yii;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

class ModelService
{
    /**
     * Finds a model by its primary key and class name.
     *
     * @param int|string $id The primary key value.
     * @param string $modelClass The fully qualified class name of the model.
     * @return ActiveRecord The found model instance.
     * @throws NotFoundHttpException if the model cannot be found.
     */
    public function findModelById(int|string $id, string $modelClass): ActiveRecord
    {
        $model = call_user_func([$modelClass, 'findOne'], ['id' => $id]);

        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested resource does not exist.');
    }

    /**
     * Finds models by an attribute or set of attributes.
     *
     * @param array $conditions Key-value pairs of attributes to search by.
     * @param string $modelClass The fully qualified class name of the model.
     * @return ActiveRecord[] The found models.
     */
    public function findModelsByAttributes(array $conditions, string $modelClass): array
    {
        return call_user_func([$modelClass, 'findAll'], $conditions);
    }

    /**
     * Deletes a single model safely.
     *
     * @param ActiveRecord $model The model to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteModelSafely(ActiveRecord $model): bool
    {
        try {
            $model->delete();
            return true;
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
            return false;
        }
    }

    /**
     * Deletes multiple models by a set of conditions.
     *
     * @param array $conditions Key-value pairs of attributes to filter models to delete.
     * @param string $modelClass The fully qualified class name of the model.
     * @return int The number of rows deleted.
     */
    public function deleteModelsByAttributes(array $conditions, string $modelClass): int
    {
        return call_user_func([$modelClass, 'deleteAll'], $conditions);
    }
}
