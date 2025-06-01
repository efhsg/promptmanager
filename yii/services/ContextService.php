<?php

namespace app\services;

use app\models\Context;
use Throwable;
use yii\base\Component;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class ContextService extends Component
{
    /**
     * Persists the given Context model.
     *
     * @param Context $model
     * @return bool
     * @throws Exception
     */
    public function saveContext(Context $model): bool
    {
        return $model->save();
    }

    /**
     * Deletes the given Context model.
     *
     * @param Context $model
     * @return bool
     * @throws Exception|Throwable
     */
    public function deleteContext(Context $model): bool
    {
        return $model->delete() !== false;
    }

    /**
     * Fetches all contexts belonging to the given user.
     *
     * Assumes that each Context is linked to a Project that has a user_id.
     *
     * @param int $userId The ID of the user.
     * @return array An associative array of contexts mapped as [id => name].
     */
    public function fetchContexts(int $userId): array
    {
        $contexts = Context::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return ArrayHelper::map($contexts, 'id', 'name');
    }

    /**
     * Fetches the content of all contexts belonging to the given user.
     *
     * @param int $userId The ID of the user.
     * @return array An associative array of contexts mapped as [id => content].
     */
    public function fetchContextsContent(int $userId): array
    {
        $contexts = Context::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId])
            ->all();

        return ArrayHelper::map($contexts, 'id', 'content');
    }

    /**
     * Fetches all contexts belonging to the given user and project.
     *
     * @param int $userId The ID of the user.
     * @param int|null $projectId The ID of the project.
     * @return array An associative array of contexts mapped as [id => name].
     */
    public function fetchProjectContexts(int $userId, ?int $projectId): array
    {
        $query = Context::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId]);
        if ($projectId !== null) {
            $query->andWhere(['project.id' => $projectId]);
        }
        $contexts = $query->orderBy(['name' => SORT_ASC])->all();
        return ArrayHelper::map($contexts, 'id', 'name');
    }

    public function fetchContextsContentById(int $userId, array $contextIds): array
    {
        if (empty($contextIds)) {
            return [];
        }

        $contexts = Context::find()
            ->joinWith('project')
            ->where(['project.user_id' => $userId])
            ->andWhere(['context.id' => $contextIds])
            ->all();

        return ArrayHelper::map($contexts, 'id', 'content');
    }

}
