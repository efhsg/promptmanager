<?php

namespace app\models;

use yii\base\Model;
use yii\db\Exception;

/**
 * @property mixed $id
 */
class PromptInstanceForm extends Model
{
    public ?int $template_id = null;
    public ?string $final_prompt = null;
    public array $context_ids = [];

    public function rules(): array
    {
        return [
            [['template_id', 'final_prompt'], 'required'],
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
        $promptInstance->final_prompt = $this->final_prompt;

        // Save the PromptInstance model.
        // You may need additional logic to process context_ids separately.
        return $promptInstance->save();
    }
}
