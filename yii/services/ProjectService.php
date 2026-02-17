<?php

namespace app\services;

use app\models\Project;
use app\models\ProjectLinkedProject;
use common\enums\LogCategory;
use Yii;
use yii\helpers\ArrayHelper;

class ProjectService
{
    /**
     * Finds an existing project by name or creates a new one.
     *
     * @return int|array|null Project ID, null if no name provided, or array of error messages on failure
     */
    public function findOrCreateByName(int $userId, ?string $projectName): int|array|null
    {
        if ($projectName === null || $projectName === '') {
            return null;
        }

        $project = Project::find()
            ->forUser($userId)
            ->withName($projectName)
            ->one();

        if ($project) {
            return $project->id;
        }

        // Auto-create project
        $project = new Project([
            'user_id' => $userId,
            'name' => $projectName,
        ]);

        if ($project->save()) {
            return $project->id;
        }

        // Return validation errors or log DB failure
        $errors = $project->getFirstErrors();
        if (empty($errors)) {
            Yii::error("Failed to auto-create project '$projectName' for user $userId: DB save failed without validation errors", LogCategory::DATABASE->value);
            return ['Failed to create project due to a server error.'];
        }

        return array_values($errors);
    }

    public function fetchProjectsList(int $userId): array
    {
        return ArrayHelper::map(
            Project::find()
                ->forUser($userId)
                ->orderedByName()
                ->all() ?: [],
            'id',
            'name'
        );
    }

    public function fetchAvailableProjectsForLinking(?int $excludeProjectId, int $userId): array
    {
        return ArrayHelper::map(
            Project::find()
                ->availableForLinking($excludeProjectId, $userId)
                ->all() ?: [],
            'id',
            'name'
        );
    }

    public function syncLinkedProjects(Project $project, array $linkedProjectIds): void
    {
        $existingLinks = ProjectLinkedProject::find()
            ->where(['project_id' => $project->id])
            ->indexBy('linked_project_id')
            ->all();

        $toDelete = array_diff(array_keys($existingLinks), $linkedProjectIds);
        foreach ($toDelete as $linkedProjectId) {
            $existingLinks[$linkedProjectId]->delete();
        }

        $toAdd = array_diff($linkedProjectIds, array_keys($existingLinks));
        foreach ($toAdd as $linkedProjectId) {
            $link = new ProjectLinkedProject([
                'project_id' => $project->id,
                'linked_project_id' => $linkedProjectId,
            ]);
            $link->save();
        }
    }
}
