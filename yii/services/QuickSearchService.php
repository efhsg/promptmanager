<?php

namespace app\services;

use app\models\Context;
use app\models\Field;
use app\models\Note;
use app\models\PromptInstance;
use app\models\PromptTemplate;
use yii\base\Component;
use yii\helpers\Url;

/**
 * Service for quick search across multiple entity types.
 * Searches Contexts, Fields, Templates, Generated Prompts, and Notes.
 */
class QuickSearchService extends Component
{
    private const DEFAULT_LIMIT = 5;

    /**
     * Search across all entity types for the given term.
     *
     * @return array{contexts: array, fields: array, templates: array, instances: array, notes: array}
     */
    public function search(string $term, int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        $term = trim($term);
        if (strlen($term) < 2) {
            return $this->emptyResults();
        }

        return [
            'contexts' => $this->searchContexts($term, $userId, $limit),
            'fields' => $this->searchFields($term, $userId, $limit),
            'templates' => $this->searchTemplates($term, $userId, $limit),
            'instances' => $this->searchInstances($term, $userId, $limit),
            'notes' => $this->searchNotes($term, $userId, $limit),
        ];
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchContexts(string $term, int $userId, int $limit): array
    {
        $contexts = Context::find()
            ->forUser($userId)
            ->searchByTerm($term)
            ->prioritizeNameMatch($term)
            ->limit($limit)
            ->all();

        return array_map(fn(Context $context) => [
            'id' => $context->id,
            'type' => 'context',
            'name' => $context->name,
            'subtitle' => $context->project->name ?? '',
            'url' => Url::to(['/context/view', 'id' => $context->id, 'p' => $context->project_id]),
        ], $contexts);
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchFields(string $term, int $userId, int $limit): array
    {
        $fields = Field::find()
            ->forUser($userId)
            ->searchByTerm($term)
            ->prioritizeNameMatch($term)
            ->limit($limit)
            ->all();

        return array_map(fn(Field $field) => [
            'id' => $field->id,
            'type' => 'field',
            'name' => $field->label ?: $field->name,
            'subtitle' => $field->project->name ?? 'Global',
            'url' => Url::to(['/field/view', 'id' => $field->id, 'p' => $field->project_id]),
        ], $fields);
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchTemplates(string $term, int $userId, int $limit): array
    {
        $templates = PromptTemplate::find()
            ->forUser($userId)
            ->searchByTerm($term)
            ->prioritizeNameMatch($term)
            ->limit($limit)
            ->all();

        return array_map(fn(PromptTemplate $template) => [
            'id' => $template->id,
            'type' => 'template',
            'name' => $template->name,
            'subtitle' => $template->project->name ?? '',
            'url' => Url::to(['/prompt-template/view', 'id' => $template->id, 'p' => $template->project_id]),
        ], $templates);
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchInstances(string $term, int $userId, int $limit): array
    {
        $instances = PromptInstance::find()
            ->forUser($userId)
            ->searchByTerm($term)
            ->prioritizeNameMatch($term)
            ->limit($limit)
            ->all();

        return array_map(fn(PromptInstance $instance) => [
            'id' => $instance->id,
            'type' => 'instance',
            'name' => $instance->label ?: 'Generated #' . $instance->id,
            'subtitle' => $instance->template->project->name ?? '',
            'url' => Url::to(['/prompt-instance/view', 'id' => $instance->id, 'p' => $instance->template->project_id]),
        ], $instances);
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchNotes(string $term, int $userId, int $limit): array
    {
        $notes = Note::find()
            ->forUser($userId)
            ->searchByTerm($term)
            ->prioritizeNameMatch($term)
            ->limit($limit)
            ->all();

        return array_map(fn(Note $note) => [
            'id' => $note->id,
            'type' => 'note',
            'name' => $note->name,
            'subtitle' => $note->project->name ?? 'No project',
            'url' => $note->parent_id
                ? Url::to(['/note/view', 'id' => $note->parent_id, 'p' => $note->project_id])
                : Url::to(['/note/view', 'id' => $note->id, 'p' => $note->project_id]),
        ], $notes);
    }

    /**
     * @return array{contexts: array, fields: array, templates: array, instances: array, notes: array}
     */
    private function emptyResults(): array
    {
        return [
            'contexts' => [],
            'fields' => [],
            'templates' => [],
            'instances' => [],
            'notes' => [],
        ];
    }
}
