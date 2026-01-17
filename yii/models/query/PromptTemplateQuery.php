<?php

namespace app\models\query;

use app\models\PromptTemplate;
use yii\db\ActiveQuery;

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

    public function forProject(int $projectId): self
    {
        return $this->andWhere([PromptTemplate::tableName() . '.project_id' => $projectId]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([PromptTemplate::tableName() . '.name' => SORT_ASC]);
    }
}
