<?php

namespace app\models\query;

use app\models\PromptInstance;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Query class for PromptInstance model, encapsulating reusable query conditions.
 *
 * @extends ActiveQuery<PromptInstance>
 */
class PromptInstanceQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this
            ->innerJoinWith(['template', 'template.project'])
            ->andWhere(['project.user_id' => $userId]);
    }

    public function searchByTerm(string $term): self
    {
        return $this->andWhere(['or',
            ['like', 'prompt_instance.label', $term],
            ['like', 'prompt_instance.search_text', $term],
        ]);
    }

    /**
     * Orders results so label matches appear before content-only matches.
     */
    public function prioritizeNameMatch(string $term): self
    {
        $escapedTerm = '%' . addcslashes($term, '%_\\') . '%';

        return $this->orderBy(new Expression(
            "CASE WHEN prompt_instance.label LIKE :nameTerm THEN 0 ELSE 1 END ASC, prompt_instance.created_at DESC",
            [':nameTerm' => $escapedTerm]
        ));
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', 'prompt_instance.label', $keyword],
                ['like', 'prompt_instance.search_text', $keyword],
            ];
        }

        return $this->andWhere($conditions);
    }

    public function forTemplate(int $templateId): self
    {
        return $this->andWhere([PromptInstance::tableName() . '.template_id' => $templateId]);
    }

    public function orderedByCreated(): self
    {
        return $this->orderBy([PromptInstance::tableName() . '.created_at' => SORT_DESC]);
    }
}
