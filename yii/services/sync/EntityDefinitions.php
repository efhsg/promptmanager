<?php

namespace app\services\sync;

/**
 * Entity definitions for database sync operations.
 */
class EntityDefinitions
{
    public static function getAll(): array
    {
        return [
            'project' => [
                'table' => 'project',
                'naturalKeys' => ['name', 'user_id'],
                'foreignKeys' => [],
                'columns' => [
                    'user_id', 'name', 'description', 'root_directory',
                    'allowed_file_extensions', 'blacklisted_directories',
                    'prompt_instance_copy_format', 'label',
                    'created_at', 'updated_at', 'deleted_at',
                ],
            ],
            'context' => [
                'table' => 'context',
                'naturalKeys' => ['name'],
                'foreignKeys' => ['project_id' => 'project'],
                'columns' => [
                    'project_id', 'name', 'content', 'is_default', 'share', 'order',
                    'created_at', 'updated_at',
                ],
            ],
            'field' => [
                'table' => 'field',
                'naturalKeys' => ['name', 'user_id'],
                'foreignKeys' => ['project_id' => 'project'],
                'columns' => [
                    'user_id', 'project_id', 'name', 'type', 'content', 'share',
                    'label', 'render_label', 'created_at', 'updated_at',
                ],
            ],
            'field_option' => [
                'table' => 'field_option',
                'naturalKeys' => ['value'],
                'foreignKeys' => ['field_id' => 'field'],
                'columns' => [
                    'field_id', 'value', 'label', 'selected_by_default', 'order',
                    'created_at', 'updated_at',
                ],
            ],
            'prompt_template' => [
                'table' => 'prompt_template',
                'naturalKeys' => ['name'],
                'foreignKeys' => ['project_id' => 'project'],
                'columns' => [
                    'project_id', 'name', 'template_body',
                    'created_at', 'updated_at',
                ],
            ],
            'scratch_pad' => [
                'table' => 'scratch_pad',
                'naturalKeys' => ['name', 'user_id'],
                'foreignKeys' => ['project_id' => 'project'],
                'columns' => [
                    'user_id', 'project_id', 'name', 'content',
                    'created_at', 'updated_at',
                ],
            ],
            'prompt_instance' => [
                'table' => 'prompt_instance',
                'naturalKeys' => ['label', 'created_at'],
                'foreignKeys' => ['template_id' => 'prompt_template'],
                'columns' => [
                    'template_id', 'label', 'final_prompt',
                    'created_at', 'updated_at',
                ],
            ],
            'template_field' => [
                'table' => 'template_field',
                'naturalKeys' => [],
                'foreignKeys' => [
                    'template_id' => 'prompt_template',
                    'field_id' => 'field',
                ],
                'columns' => ['template_id', 'field_id'],
            ],
            'project_linked_project' => [
                'table' => 'project_linked_project',
                'naturalKeys' => [],
                'foreignKeys' => [
                    'project_id' => 'project',
                    'linked_project_id' => 'project',
                ],
                'columns' => ['project_id', 'linked_project_id'],
            ],
        ];
    }

    public static function getSyncOrder(): array
    {
        return [
            'project',
            'project_linked_project',
            'context',
            'field',
            'field_option',
            'prompt_template',
            'template_field',
            'scratch_pad',
            'prompt_instance',
        ];
    }
}
