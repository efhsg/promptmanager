<?php

namespace app\models\query;

use app\models\Note;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * @extends ActiveQuery<Note>
 */
class NoteQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere([Note::tableName() . '.user_id' => $userId]);
    }

    public function forProject(?int $projectId): self
    {
        if ($projectId === null) {
            return $this->andWhere([Note::tableName() . '.project_id' => null]);
        }

        return $this->andWhere([Note::tableName() . '.project_id' => $projectId]);
    }

    public function forUserWithProject(int $userId, ?int $projectId): self
    {
        $query = $this->forUser($userId);

        if ($projectId !== null) {
            return $query->forProject($projectId);
        }

        return $query->andWhere([Note::tableName() . '.project_id' => null]);
    }

    public function forParent(?int $parentId): self
    {
        return $this->andWhere([Note::tableName() . '.parent_id' => $parentId]);
    }

    public function topLevel(): self
    {
        return $this->andWhere([Note::tableName() . '.parent_id' => null]);
    }

    public function withChildren(): self
    {
        return $this->with('children');
    }

    public function withChildCount(): self
    {
        $table = Note::tableName();

        return $this->addSelect(["{$table}.*"])
            ->addSelect(["child_count" => new Expression(
                "(SELECT COUNT(*) FROM {$table} c WHERE c.parent_id = {$table}.id)"
            )]);
    }

    public function orderedByUpdated(): self
    {
        return $this->orderBy([Note::tableName() . '.updated_at' => SORT_DESC]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([Note::tableName() . '.name' => SORT_ASC]);
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', Note::tableName() . '.name', $term],
            ['like', Note::tableName() . '.content', $term],
        ]);
    }

    /**
     * Orders results so name matches appear before content-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $tableName = Note::tableName();
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
                ['like', Note::tableName() . '.name', $keyword],
                ['like', Note::tableName() . '.content', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }
}
