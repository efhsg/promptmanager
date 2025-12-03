<?php

namespace app\services;

use app\models\Project;
use app\models\ProjectLinkedProject;
use yii\helpers\ArrayHelper;

class ProjectService
{
    public function fetchProjectsList(int $userId): array
    {
        return ArrayHelper::map(
            Project::find()
                ->where(['user_id' => $userId])
                ->orderBy(['name' => SORT_ASC])
                ->all() ?: [],
            'id',
            'name'
        );
    }

    public function fetchAvailableProjectsForLinking(?int $excludeProjectId, int $userId): array
    {
        return ArrayHelper::map(
            Project::findAvailableForLinking($excludeProjectId, $userId)->all() ?: [],
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
