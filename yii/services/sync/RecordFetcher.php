<?php

namespace app\services\sync;

use yii\db\Connection;
use yii\db\Query;

/**
 * Fetches records from database for sync operations, scoped by user.
 */
class RecordFetcher
{
    public function fetch(Connection $db, array $definition, int $userId, string $entity): array
    {
        $query = (new Query())
            ->select(['id', ...$definition['columns']])
            ->from($definition['table']);

        $this->applyUserScope($query, $db, $definition, $userId, $entity);

        return $query->all($db);
    }

    private function applyUserScope(Query $query, Connection $db, array $definition, int $userId, string $entity): void
    {
        if (in_array('user_id', $definition['columns'], true)) {
            $query->andWhere(['user_id' => $userId]);
            return;
        }

        $scopeMethod = 'scope' . str_replace('_', '', ucwords($entity, '_'));
        if (method_exists($this, $scopeMethod)) {
            $this->$scopeMethod($query, $db, $userId);
        }
    }

    private function scopeContext(Query $query, Connection $db, int $userId): void
    {
        $this->scopeByProject($query, $db, $userId);
    }

    private function scopePromptTemplate(Query $query, Connection $db, int $userId): void
    {
        $this->scopeByProject($query, $db, $userId);
    }

    private function scopeTemplateField(Query $query, Connection $db, int $userId): void
    {
        $templateIds = $this->getTemplateIds($db, $userId);
        if (empty($templateIds)) {
            $query->andWhere('1=0');
            return;
        }
        $query->andWhere(['template_id' => $templateIds]);
    }

    private function scopePromptInstance(Query $query, Connection $db, int $userId): void
    {
        $templateIds = $this->getTemplateIds($db, $userId);
        if (empty($templateIds)) {
            $query->andWhere('1=0');
            return;
        }
        $query->andWhere(['template_id' => $templateIds]);
    }

    private function scopeFieldOption(Query $query, Connection $db, int $userId): void
    {
        $fieldIds = (new Query())
            ->select('id')
            ->from('field')
            ->where(['user_id' => $userId])
            ->column($db);

        if (empty($fieldIds)) {
            $query->andWhere('1=0');
            return;
        }
        $query->andWhere(['field_id' => $fieldIds]);
    }

    private function scopeProjectLinkedProject(Query $query, Connection $db, int $userId): void
    {
        $this->scopeByProject($query, $db, $userId);
    }

    private function scopeByProject(Query $query, Connection $db, int $userId): void
    {
        $projectIds = $this->getProjectIds($db, $userId);
        if (empty($projectIds)) {
            $query->andWhere('1=0');
            return;
        }
        $query->andWhere(['project_id' => $projectIds]);
    }

    private function getProjectIds(Connection $db, int $userId): array
    {
        return (new Query())
            ->select('id')
            ->from('project')
            ->where(['user_id' => $userId])
            ->column($db);
    }

    private function getTemplateIds(Connection $db, int $userId): array
    {
        return (new Query())
            ->select('pt.id')
            ->from('prompt_template pt')
            ->innerJoin('project p', 'p.id = pt.project_id')
            ->where(['p.user_id' => $userId])
            ->column($db);
    }
}
