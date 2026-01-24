<?php

namespace app\models\query;

use app\models\PromptTemplate;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Query class for PromptTemplate model, encapsulating reusable query conditions.
 *
 * @extends ActiveQuery<PromptTemplate>
 */
class PromptTemplateQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this
            ->innerJoinWith('project')
            ->andWhere(['project.user_id' => $userId]);
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', 'prompt_template.name', $term],
            ['like', 'prompt_template.template_body', $term],
        ]);
    }

    /**
     * Orders results so name matches appear before body-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $escapedTerm = '%' . addcslashes($term, '%_\\') . '%';

        return $this->orderBy(new Expression(
            "CASE WHEN prompt_template.name LIKE :nameTerm THEN 0 ELSE 1 END ASC, prompt_template.updated_at DESC",
            [':nameTerm' => $escapedTerm]
        ));
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', 'prompt_template.name', $keyword],
                ['like', 'prompt_template.template_body', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }

    public function forProject(int $projectId): self
    {
        return $this->andWhere([PromptTemplate::tableName() . '.project_id' => $projectId]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([PromptTemplate::tableName() . '.name' => SORT_ASC]);
    }
}
