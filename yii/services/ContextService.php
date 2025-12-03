<?php

namespace app\services;

use app\models\Context;
use app\models\query\ContextQuery;
use Throwable;
use yii\base\Component;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

/**
 * Service for managing Context models: saving, deleting and fetching contexts
 * for a user or project.
 */
class ContextService extends Component
{
    /**
     * Persist the given Context model.
     *
     * @throws Exception
     */
    public function saveContext(Context $model): bool
    {
        return $model->save();
    }

    /**
     * Delete the given Context model.
     *
     * @throws Exception|Throwable
     */
    public function deleteContext(Context $model): bool
    {
        return $model->delete() !== false;
    }

    /**
     * Fetch all contexts belonging to the given user.
     * Returns an associative array of contexts mapped as [id => name].
     */
    public function fetchContexts(int $userId): array
    {
        $contexts = $this->createUserContextQuery($userId)
            ->orderedByName()
            ->all();

        return ArrayHelper::map($contexts, 'id', 'name');
    }

    /**
     * Fetch the content of all contexts belonging to the given user.
     * Returns an associative array of contexts mapped as [id => content].
     */
    public function fetchContextsContent(int $userId): array
    {
        $contexts = $this->createUserContextQuery($userId)
            ->all();

        return ArrayHelper::map($contexts, 'id', 'content');
    }

    /**
     * Fetch all contexts belonging to the given user and project.
     * Includes all contexts from the current project and only shared contexts
     * from linked projects. Returns contexts grouped by project name only when
     * linked projects have contexts.
     */
    public function fetchProjectContexts(int $userId, ?int $projectId): array
    {
        if ($projectId !== null) {
            $linkedProjectIds = ContextQuery::linkedProjectIds($projectId);
            $contexts = $this->createUserContextQuery($userId)
                ->forProjectWithLinkedSharing($projectId, $linkedProjectIds)
                ->orderedByName()
                ->all();

            if (empty($contexts)) {
                return [];
            }

            $grouped = [];
            $currentProjectContexts = [];
            $currentProjectName = null;
            $linkedProjectsContexts = [];
            /** @var Context[] $contexts */
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

        $contexts = $this->createUserContextQuery($userId)
            ->orderedByName()
            ->all();

        return ArrayHelper::map($contexts, 'id', 'name');
    }

    public function fetchContextsContentById(int $userId, array $contextIds): array
    {
        if (empty($contextIds)) {
            return [];
        }

        $contexts = $this->createUserContextQuery($userId)
            ->withIds($contextIds)
            ->all();

        return ArrayHelper::map($contexts, 'id', 'content');
    }

    public function fetchDefaultContextIds(int $userId, ?int $projectId): array
    {
        $query = $this->createUserContextQuery($userId)
            ->select(Context::tableName() . '.id')
            ->onlyDefault();

        if ($projectId !== null) {
            $query->forProject($projectId);
        }

        return $query->column();
    }

    private function createUserContextQuery(int $userId): ContextQuery
    {
        return Context::find()
            ->forUser($userId);
    }
}
