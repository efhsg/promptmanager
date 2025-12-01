<?php

namespace app\models;

use app\models\PromptTemplate;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property mixed $id
 */
class PromptInstanceForm extends Model
{
    public ?int $template_id = null;
    public ?string $label = null;
    public ?string $final_prompt = null;
    public array $context_ids = [];

    public function rules(): array
    {
        return [
            [['template_id', 'final_prompt'], 'required'],
            ['label', 'string', 'max' => 255],
            [['context_ids'], 'safe'], // Allows assignment without persisting
        ];
    }

    /**
     * Saves the form data to a PromptInstance record.
     *
     * @return bool Whether the model was saved successfully.
     * @throws Exception
     */
    public function save(): bool
    {
        $promptInstance = new PromptInstance();
        $promptInstance->template_id = $this->template_id;
        $promptInstance->label = $this->label;
        $promptInstance->final_prompt = $this->final_prompt;
        $promptInstance->project_id = PromptTemplate::find()
            ->select('project_id')
            ->where(['id' => $this->template_id])
            ->scalar();

        // Save the PromptInstance model.
        // You may need additional logic to process context_ids separately.
        return $promptInstance->save();
    }
}
