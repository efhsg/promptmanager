<?php

namespace app\services;

use app\models\Context;
use app\models\Field;
use app\models\PromptInstance;
use app\models\PromptTemplate;
use app\models\ScratchPad;
use common\enums\SearchMode;
use yii\base\Component;
use yii\helpers\Url;

/**
 * Service for advanced search with entity type filtering and multiple search modes.
 */
class AdvancedSearchService extends Component
{
    private const DEFAULT_LIMIT = 10;
    private const MIN_TERM_LENGTH = 2;

    public const TYPE_CONTEXTS = 'contexts';
    public const TYPE_FIELDS = 'fields';
    public const TYPE_TEMPLATES = 'templates';
    public const TYPE_INSTANCES = 'instances';
    public const TYPE_SCRATCH_PADS = 'scratchPads';

    public const ALL_TYPES = [
        self::TYPE_CONTEXTS,
        self::TYPE_FIELDS,
        self::TYPE_TEMPLATES,
        self::TYPE_INSTANCES,
        self::TYPE_SCRATCH_PADS,
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CONTEXTS => 'Contexts',
            self::TYPE_FIELDS => 'Fields',
            self::TYPE_TEMPLATES => 'Templates',
            self::TYPE_INSTANCES => 'Generated Prompts',
            self::TYPE_SCRATCH_PADS => 'Scratch Pads',
        ];
    }

    public function search(
        string $term,
        int $userId,
        array $types = [],
        SearchMode $mode = SearchMode::PHRASE,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $term = trim($term);
        if (strlen($term) < self::MIN_TERM_LENGTH) {
            return $this->emptyResults();
        }

        $types = is_array($types) ? $types : [$types];
        $searchTypes = empty($types) ? self::ALL_TYPES : array_intersect($types, self::ALL_TYPES);
        $keywords = $mode === SearchMode::KEYWORDS ? $this->extractKeywords($term) : [];

        return [
            'contexts' => in_array(self::TYPE_CONTEXTS, $searchTypes, true)
                ? $this->searchContexts($term, $keywords, $userId, $mode, $limit)
                : [],
            'fields' => in_array(self::TYPE_FIELDS, $searchTypes, true)
                ? $this->searchFields($term, $keywords, $userId, $mode, $limit)
                : [],
            'templates' => in_array(self::TYPE_TEMPLATES, $searchTypes, true)
                ? $this->searchTemplates($term, $keywords, $userId, $mode, $limit)
                : [],
            'instances' => in_array(self::TYPE_INSTANCES, $searchTypes, true)
                ? $this->searchInstances($term, $keywords, $userId, $mode, $limit)
                : [],
            'scratchPads' => in_array(self::TYPE_SCRATCH_PADS, $searchTypes, true)
                ? $this->searchScratchPads($term, $keywords, $userId, $mode, $limit)
                : [],
        ];
    }

    /**
     * @return string[]
     */
    private function extractKeywords(string $term): array
    {
        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($words, fn(string $word) => strlen($word) >= self::MIN_TERM_LENGTH));
    }

    /**
     * @param string[] $keywords
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchContexts(string $term, array $keywords, int $userId, SearchMode $mode, int $limit): array
    {
        $query = Context::find()
            ->forUser($userId)
            ->limit($limit);

        if ($mode === SearchMode::KEYWORDS && !empty($keywords)) {
            $query->searchByKeywords($keywords);
        } else {
            $query->searchByTerm($term);
        }
        $query->prioritizeNameMatch($term);

        return array_map(fn(Context $context) => [
            'id' => $context->id,
            'type' => 'context',
            'name' => $context->name,
            'subtitle' => $context->project->name ?? '',
            'url' => Url::to(['/context/view', 'id' => $context->id, 'p' => $context->project_id]),
        ], $query->all());
    }

    /**
     * @param string[] $keywords
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchFields(string $term, array $keywords, int $userId, SearchMode $mode, int $limit): array
    {
        $query = Field::find()
            ->forUser($userId)
            ->limit($limit);

        if ($mode === SearchMode::KEYWORDS && !empty($keywords)) {
            $query->searchByKeywords($keywords);
        } else {
            $query->searchByTerm($term);
        }
        $query->prioritizeNameMatch($term);

        return array_map(fn(Field $field) => [
            'id' => $field->id,
            'type' => 'field',
            'name' => $field->label ?: $field->name,
            'subtitle' => $field->project->name ?? 'Global',
            'url' => Url::to(['/field/view', 'id' => $field->id, 'p' => $field->project_id]),
        ], $query->all());
    }

    /**
     * @param string[] $keywords
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchTemplates(string $term, array $keywords, int $userId, SearchMode $mode, int $limit): array
    {
        $query = PromptTemplate::find()
            ->forUser($userId)
            ->limit($limit);

        if ($mode === SearchMode::KEYWORDS && !empty($keywords)) {
            $query->searchByKeywords($keywords);
        } else {
            $query->searchByTerm($term);
        }
        $query->prioritizeNameMatch($term);

        return array_map(fn(PromptTemplate $template) => [
            'id' => $template->id,
            'type' => 'template',
            'name' => $template->name,
            'subtitle' => $template->project->name ?? '',
            'url' => Url::to(['/prompt-template/view', 'id' => $template->id, 'p' => $template->project_id]),
        ], $query->all());
    }

    /**
     * @param string[] $keywords
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchInstances(string $term, array $keywords, int $userId, SearchMode $mode, int $limit): array
    {
        $query = PromptInstance::find()
            ->forUser($userId)
            ->limit($limit);

        if ($mode === SearchMode::KEYWORDS && !empty($keywords)) {
            $query->searchByKeywords($keywords);
        } else {
            $query->searchByTerm($term);
        }
        $query->prioritizeNameMatch($term);

        return array_map(fn(PromptInstance $instance) => [
            'id' => $instance->id,
            'type' => 'instance',
            'name' => $instance->label ?: 'Generated #' . $instance->id,
            'subtitle' => $instance->template->project->name ?? '',
            'url' => Url::to(['/prompt-instance/view', 'id' => $instance->id, 'p' => $instance->template->project_id]),
        ], $query->all());
    }

    /**
     * @param string[] $keywords
     * @return array<int, array{id: int, type: string, name: string, subtitle: string, url: string}>
     */
    private function searchScratchPads(string $term, array $keywords, int $userId, SearchMode $mode, int $limit): array
    {
        $query = ScratchPad::find()
            ->forUser($userId)
            ->limit($limit);

        if ($mode === SearchMode::KEYWORDS && !empty($keywords)) {
            $query->searchByKeywords($keywords);
        } else {
            $query->searchByTerm($term);
        }
        $query->prioritizeNameMatch($term);

        return array_map(fn(ScratchPad $pad) => [
            'id' => $pad->id,
            'type' => 'scratchPad',
            'name' => $pad->name,
            'subtitle' => $pad->project->name ?? 'No project',
            'url' => Url::to(['/scratch-pad/view', 'id' => $pad->id, 'p' => $pad->project_id]),
        ], $query->all());
    }

    /**
     * @return array{contexts: array, fields: array, templates: array, instances: array, scratchPads: array}
     */
    private function emptyResults(): array
    {
        return [
            'contexts' => [],
            'fields' => [],
            'templates' => [],
            'instances' => [],
            'scratchPads' => [],
        ];
    }
}
