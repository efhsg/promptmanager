<?php

namespace app\models\query;

use app\models\Context;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\db\Expression;

/**
 * @extends ActiveQuery<Context>
 */
class ContextQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this
            ->joinWith('project')
            ->andWhere(['project.user_id' => $userId]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([Context::tableName() . '.name' => SORT_ASC]);
    }

    public function orderedByOrder(): self
    {
        return $this->orderBy([
            Context::tableName() . '.order' => SORT_ASC,
            Context::tableName() . '.id' => SORT_ASC,
        ]);
    }

    public function defaultOrdering(): self
    {
        return $this->orderBy([
            Context::tableName() . '.order' => SORT_ASC,
            Context::tableName() . '.is_default' => SORT_DESC,
            Context::tableName() . '.name' => SORT_ASC,
        ]);
    }

    public function withIds(array $contextIds): self
    {
        return $this->andWhere([Context::tableName() . '.id' => $contextIds]);
    }

    public function onlyDefault(): self
    {
        return $this->andWhere([Context::tableName() . '.is_default' => 1]);
    }

    public function forProject(int $projectId): self
    {
        return $this->andWhere([Context::tableName() . '.project_id' => $projectId]);
    }

    public function forProjectWithLinkedSharing(int $projectId, array $linkedProjectIds): self
    {
        return $this->andWhere([
            'or',
            [Context::tableName() . '.project_id' => $projectId],
            [
                'and',
                [Context::tableName() . '.share' => 1],
                [Context::tableName() . '.project_id' => $linkedProjectIds],
            ],
        ]);
    }

    public static function linkedProjectIds(int $projectId): array
    {
        return (new Query())
            ->select('linked_project_id')
            ->from('project_linked_project')
            ->where(['project_id' => $projectId])
            ->column();
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', Context::tableName() . '.name', $term],
            ['like', Context::tableName() . '.search_text', $term],
        ]);
    }

    /**
     * Orders results so name matches appear before content-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $tableName = Context::tableName();
        $escapedTerm = '%' . addcslashes($term, '%_\\') . '%';

        return $this->orderBy(new Expression(
            "CASE WHEN {$tableName}.name LIKE :nameTerm THEN 0 ELSE 1 END ASC, {$tableName}.updated_at DESC",
            [':nameTerm' => $escapedTerm]
        ));
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', Context::tableName() . '.name', $keyword],
                ['like', Context::tableName() . '.search_text', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }
}
