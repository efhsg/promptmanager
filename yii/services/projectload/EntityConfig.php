<?php

namespace app\services\projectload;

/**
 * Entity configuration for project load operations.
 *
 * Defines entities, their FK relationships, insert order, and column excludes.
 * Column lists are determined dynamically via SchemaInspector — only structural
 * metadata (FKs, excludes, overrides) is configured here.
 */
class EntityConfig
{
    /** Columns excluded from load (set to NULL) — machine-specific values. */
    public const EXCLUDED_COLUMNS = [
        'project' => ['root_directory', 'claude_options', 'claude_context'],
    ];

    /** Columns overridden with a fixed value during load. */
    public const COLUMN_OVERRIDES = [
        'project' => ['user_id'],
        'field' => ['user_id'],
        'scratch_pad' => ['user_id'],
    ];

    /**
     * Returns entity definitions with FK relationships and source queries.
     *
     * @return array<string, array{table: string, foreignKeys: array, parentKey: string|null}>
     */
    public static function getEntities(): array
    {
        return [
            'project' => [
                'table' => 'project',
                'foreignKeys' => [],
                'parentKey' => null,
            ],
            'context' => [
                'table' => 'context',
                'foreignKeys' => ['project_id' => 'project'],
                'parentKey' => 'project_id',
            ],
            'field' => [
                'table' => 'field',
                'foreignKeys' => ['project_id' => 'project'],
                'parentKey' => 'project_id',
            ],
            'field_option' => [
                'table' => 'field_option',
                'foreignKeys' => ['field_id' => 'field'],
                'parentKey' => 'field_id',
            ],
            'prompt_template' => [
                'table' => 'prompt_template',
                'foreignKeys' => ['project_id' => 'project'],
                'parentKey' => 'project_id',
            ],
            'template_field' => [
                'table' => 'template_field',
                'foreignKeys' => [
                    'template_id' => 'prompt_template',
                    'field_id' => 'field',
                ],
                'parentKey' => 'template_id',
            ],
            'prompt_instance' => [
                'table' => 'prompt_instance',
                'foreignKeys' => ['template_id' => 'prompt_template'],
                'parentKey' => 'template_id',
            ],
            'scratch_pad' => [
                'table' => 'scratch_pad',
                'foreignKeys' => ['project_id' => 'project'],
                'parentKey' => 'project_id',
            ],
            'project_linked_project' => [
                'table' => 'project_linked_project',
                'foreignKeys' => ['project_id' => 'project'],
                'parentKey' => 'project_id',
            ],
        ];
    }

    /**
     * Returns insert order respecting FK dependencies.
     *
     * @return string[]
     */
    public static function getInsertOrder(): array
    {
        return [
            'project',
            'field',
            'field_option',
            'context',
            'prompt_template',
            'template_field',
            'scratch_pad',
            'prompt_instance',
            'project_linked_project',
        ];
    }

    /**
     * Returns entity names that have auto-increment primary keys.
     * template_field has no auto-increment PK.
     *
     * @return string[]
     */
    public static function getAutoIncrementEntities(): array
    {
        return [
            'project',
            'context',
            'field',
            'field_option',
            'prompt_template',
            'prompt_instance',
            'scratch_pad',
        ];
    }

    /**
     * Returns the list of related entity tables shown in the list command.
     *
     * @return array<string, string> entity => display label
     */
    public static function getListColumns(): array
    {
        return [
            'context' => 'Ctx',
            'field' => 'Fld',
            'prompt_template' => 'Tpl',
            'prompt_instance' => 'Inst',
            'scratch_pad' => 'SP',
            'project_linked_project' => 'Links',
        ];
    }

    /**
     * Returns excluded columns for a given entity.
     *
     * @return string[]
     */
    public static function getExcludedColumns(string $entity): array
    {
        return self::EXCLUDED_COLUMNS[$entity] ?? [];
    }

    /**
     * Returns override columns for a given entity (columns that get a fixed value).
     *
     * @return string[]
     */
    public static function getOverrideColumns(string $entity): array
    {
        return self::COLUMN_OVERRIDES[$entity] ?? [];
    }
}
