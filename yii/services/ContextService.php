<?php

namespace app\services;

use app\models\Context;
use Throwable;
use Yii;
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
     * Includes contexts from the current project and all contexts from linked projects.
     * Returns contexts grouped by project name only when linked projects have contexts.
     *
     * @param int $userId The ID of the user.
     * @param int|null $projectId The ID of the project.
     * @return array If linked projects have contexts: [projectName => [id => contextName]]. Otherwise: [id => contextName].
     */
    public function fetchProjectContexts(int $userId, ?int $projectId): array
    {
        $query = $this->createUserContextQuery($userId);
        if ($projectId !== null) {
            $linkedProjectIds = $this->getLinkedProjectIds($projectId);
            $query->andWhere([
                'or',
                ['p.id' => $projectId],
                ['p.id' => $linkedProjectIds],
            ]);

            $contexts = $query->orderBy(['c.name' => SORT_ASC])->all();

            if (empty($contexts)) {
                return [];
            }

            $grouped = [];
            $currentProjectContexts = [];
            $currentProjectName = null;
            $linkedProjectsContexts = [];

            foreach ($contexts as $context) {
                $contextProjectId = $context->project_id;
                $projectName = $context->project->name;

                if ($contextProjectId === $projectId) {
                    $currentProjectContexts[$context->id] = $context->name;
                    if ($currentProjectName === null) {
                        $currentProjectName = $projectName;
                    }
                } else {
                    if (!isset($linkedProjectsContexts[$projectName])) {
                        $linkedProjectsContexts[$projectName] = [];
                    }
                    $linkedProjectsContexts[$projectName][$context->id] = $context->name;
                }
            }

            if (empty($linkedProjectsContexts)) {
                return $currentProjectContexts;
            }

            if (!empty($currentProjectContexts)) {
                $grouped[$currentProjectName ?? 'Current Project'] = $currentProjectContexts;
            }

            foreach ($linkedProjectsContexts as $projectName => $projectContexts) {
                $grouped[$projectName] = $projectContexts;
            }

            return $grouped;
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
            ->select('c.id')
            ->andWhere(['c.is_default' => 1]);
        if ($projectId !== null) {
            $query->andWhere(['p.id' => $projectId]);
        }
        return $query->column();
    }

    private function createUserContextQuery(int $userId): ActiveQuery
    {
        return Context::find()
            ->alias('c')
            ->joinWith(['project p'])
            ->where(['p.user_id' => $userId]);
    }

    private function getLinkedProjectIds(int $projectId): array
    {
        return Yii::$app->db->createCommand()
            ->select('linked_project_id')
            ->from('project_linked_project')
            ->where(['project_id' => $projectId])
            ->queryColumn();
    }
}
