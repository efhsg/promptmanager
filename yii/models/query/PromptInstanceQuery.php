<?php

namespace app\models\query;

use app\models\PromptInstance;
use yii\db\ActiveQuery;

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
            ['like', 'prompt_instance.final_prompt', $term],
        ]);
    }

    public function searchByKeywords(array $keywords): self
    {
        $conditions = ['or'];
        foreach ($keywords as $keyword) {
            $conditions[] = ['or',
                ['like', 'prompt_instance.label', $keyword],
                ['like', 'prompt_instance.final_prompt', $keyword],
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
