<?php

namespace app\services;

use app\models\Context;
use app\models\ProjectLinkedProject;
use Throwable;
use yii\base\Component;
use yii\db\ActiveQuery;
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
        $contexts = $this->createUserContextQuery($userId)
            ->orderBy(['c.name' => SORT_ASC])
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
        $contexts = $this->createUserContextQuery($userId)->all();

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
        $query = $this->createUserContextQuery($userId);
        if ($projectId !== null) {
            $linkedProjectIds = $this->getLinkedProjectIds($projectId, $userId);

            $conditions = [['p.id' => $projectId]];

            if (!empty($linkedProjectIds)) {
                $conditions[] = [
                    'and',
                    ['p.id' => $linkedProjectIds],
                    ['c.share' => 1],
                ];
            }

            $query->andWhere(['or', ...$conditions]);
        }
        $contexts = $query->orderBy(['c.name' => SORT_ASC])->all();
        return ArrayHelper::map($contexts, 'id', 'name');
    }

    public function fetchContextsContentById(int $userId, array $contextIds): array
    {
        if (empty($contextIds)) {
            return [];
        }

        $contexts = $this->createUserContextQuery($userId)
            ->andWhere(['c.id' => $contextIds])
            ->all();

        return ArrayHelper::map($contexts, 'id', 'content');
    }

    public function fetchDefaultContextIds(int $userId, ?int $projectId): array
    {
        $query = $this->createUserContextQuery($userId)
            ->select('c.id');

        if ($projectId !== null) {
            $query->andWhere([
                'p.id' => $projectId,
                'c.is_default' => 1,
            ]);

            return $query->column();
        }

        $query->andWhere(['c.is_default' => 1]);

        return $query->column();
    }

    private function getLinkedProjectIds(int $projectId, int $userId): array
    {
        return ProjectLinkedProject::find()
            ->linkedProjectIdsFor($projectId, $userId)
            ->column();
    }

    private function createUserContextQuery(int $userId): ActiveQuery
    {
        return Context::find()
            ->alias('c')
            ->joinWith(['project p'])
            ->where(['p.user_id' => $userId]);
    }
}
