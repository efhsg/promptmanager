<?php

namespace app\services\projectload;

use yii\db\Connection;

/**
 * Loads entities from temp schema into production with FK remapping.
 *
 * Uses raw SQL inserts to preserve dump timestamps (bypassing TimestampTrait)
 * and avoid ActiveRecord lifecycle hooks (afterSave, afterDelete).
 */
class EntityLoader
{
    private Connection $db;
    private SchemaInspector $schemaInspector;
    private string $tempSchema;
    private string $productionSchema;
    private int $userId;

    /** @var array<string, array<int, int>> entity => [dumpId => localId] */
    private array $idMappings = [];

    public function __construct(
        Connection $db,
        SchemaInspector $schemaInspector,
        string $tempSchema,
        string $productionSchema,
        int $userId
    ) {
        $this->db = $db;
        $this->schemaInspector = $schemaInspector;
        $this->tempSchema = $tempSchema;
        $this->productionSchema = $productionSchema;
        $this->userId = $userId;
    }

    /**
     * Fetches records for an entity from the temp schema.
     *
     * @param int|int[] $parentIds
     * @return array<int, array>
     */
    public function fetchFromTemp(string $entity, string $parentColumn, array|int $parentIds): array
    {
        $config = EntityConfig::getEntities()[$entity];
        $table = $config['table'];

        if (is_int($parentIds)) {
            $parentIds = [$parentIds];
        }

        if (empty($parentIds)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach (array_values($parentIds) as $i => $id) {
            $key = ":p{$i}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $sql = "SELECT * FROM `{$this->tempSchema}`.`{$table}` WHERE `{$parentColumn}` IN (" . implode(',', $placeholders) . ")";

        return $this->db->createCommand($sql, $params)->queryAll();
    }

    /**
     * Fetches a single project from temp schema.
     */
    public function fetchProject(int $projectId): ?array
    {
        return $this->db->createCommand(
            "SELECT * FROM `{$this->tempSchema}`.`project` WHERE id = :id",
            [':id' => $projectId]
        )->queryOne() ?: null;
    }

    /**
     * Fetches all projects from temp schema.
     *
     * @return array[]
     */
    public function fetchAllProjects(): array
    {
        return $this->db->createCommand(
            "SELECT * FROM `{$this->tempSchema}`.`project`"
        )->queryAll();
    }

    /**
     * Counts records for an entity in temp schema related to a project.
     */
    public function countInTemp(string $table, string $parentColumn, int|array $parentIds): int
    {
        if (is_int($parentIds)) {
            $parentIds = [$parentIds];
        }
        if (empty($parentIds)) {
            return 0;
        }

        $params = [];
        $placeholders = [];
        foreach (array_values($parentIds) as $i => $id) {
            $key = ":p{$i}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        return (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->tempSchema}`.`{$table}` WHERE `{$parentColumn}` IN (" . implode(',', $placeholders) . ")",
            $params
        )->queryScalar();
    }

    /**
     * Counts all local entities for a project (for dry-run deletion report).
     *
     * @return array<string, int>
     */
    public function countLocalEntities(int $projectId): array
    {
        $counts = [];
        $schema = $this->productionSchema;

        $counts['project'] = 1;
        $counts['context'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`context` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $counts['field'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`field` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $counts['field_option'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`field_option` WHERE field_id IN (SELECT id FROM `{$schema}`.`field` WHERE project_id = :id)",
            [':id' => $projectId]
        )->queryScalar();
        $counts['prompt_template'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $counts['template_field'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`template_field` WHERE template_id IN (SELECT id FROM `{$schema}`.`prompt_template` WHERE project_id = :id)",
            [':id' => $projectId]
        )->queryScalar();
        $counts['prompt_instance'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`prompt_instance` WHERE template_id IN (SELECT id FROM `{$schema}`.`prompt_template` WHERE project_id = :id)",
            [':id' => $projectId]
        )->queryScalar();
        $counts['note'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`note` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $counts['project_linked_project'] = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`project_linked_project` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();

        return $counts;
    }

    /**
     * Deletes a local project and all related entities via raw SQL.
     *
     * Uses raw SQL instead of ActiveRecord to avoid triggering
     * Project::afterDelete() → claudeWorkspaceService->deleteWorkspace().
     */
    public function deleteLocalProject(int $projectId): void
    {
        $schema = $this->productionSchema;

        // Delete prompt_instances first (ON DELETE RESTRICT on template_id)
        $this->db->createCommand(
            "DELETE FROM `{$schema}`.`prompt_instance`
             WHERE template_id IN (SELECT id FROM `{$schema}`.`prompt_template` WHERE project_id = :id)",
            [':id' => $projectId]
        )->execute();

        // Delete project — CASCADE handles remaining children
        $this->db->createCommand(
            "DELETE FROM `{$schema}`.`project` WHERE id = :id",
            [':id' => $projectId]
        )->execute();
    }

    /**
     * Inserts an entity record into production using raw SQL.
     *
     * @param string[] $columns
     * @return int|null New ID for auto-increment entities, null for others
     */
    public function insertRecord(string $table, array $columns, array $values): ?int
    {
        $this->db->createCommand()->insert(
            "`{$this->productionSchema}`.`{$table}`",
            array_combine($columns, $values)
        )->execute();

        if (in_array($table, ['template_field', 'project_linked_project'], true)) {
            return null;
        }

        return (int) $this->db->getLastInsertID();
    }

    /**
     * Loads all records for an entity from temp, applies remapping, and inserts into production.
     *
     * @param array[] $records Records from temp schema
     * @param string[] $insertColumns Columns to insert
     * @param array<string, array{nullable: bool, default: mixed, dataType: string, extra: string}> $productionColumnInfo
     * @param string[] $tempColumns Columns available in temp schema
     * @param string[] $excludeColumns Columns to set to NULL
     * @param string[] $overrideColumns Columns to override with user_id
     * @param array<string, string> $foreignKeys FK column => entity name
     * @param bool $hasAutoIncrement Whether entity has auto-increment PK
     * @return array{inserted: int, idMap: array<int, int>, warnings: string[]}
     */
    public function loadEntityRecords(
        string $entity,
        string $table,
        array $records,
        array $insertColumns,
        array $productionColumnInfo,
        array $tempColumns,
        array $excludeColumns,
        array $overrideColumns,
        array $foreignKeys,
        bool $hasAutoIncrement
    ): array {
        $inserted = 0;
        $idMap = [];
        $warnings = [];
        $tempColumnSet = array_flip($tempColumns);

        foreach ($records as $record) {
            $dumpId = $record['id'] ?? null;
            $values = [];

            foreach ($insertColumns as $column) {
                // Excluded columns → NULL
                if (in_array($column, $excludeColumns, true)) {
                    $values[] = null;
                    continue;
                }

                // Override columns (user_id) → fixed value
                if (in_array($column, $overrideColumns, true)) {
                    $values[] = $this->userId;
                    continue;
                }

                // FK remapping
                if (isset($foreignKeys[$column])) {
                    $fkEntity = $foreignKeys[$column];
                    $sourceValue = $record[$column] ?? null;

                    if ($sourceValue !== null) {
                        $mappedId = $this->getMappedId($fkEntity, (int) $sourceValue);
                        if ($mappedId !== null) {
                            $values[] = $mappedId;
                        } else {
                            $values[] = $sourceValue;
                        }
                    } else {
                        $values[] = null;
                    }
                    continue;
                }

                // Column exists in temp schema → use dump value
                if (isset($tempColumnSet[$column]) && array_key_exists($column, $record)) {
                    $values[] = $record[$column];
                    continue;
                }

                // Column missing in temp schema → fallback
                $info = $productionColumnInfo[$column] ?? [];
                $values[] = SchemaInspector::getPhpFallbackValue($info);
            }

            $newId = $this->insertRecord($table, $insertColumns, $values);

            if ($hasAutoIncrement && $dumpId !== null && $newId !== null) {
                $idMap[(int) $dumpId] = $newId;
                $this->addIdMapping($entity, (int) $dumpId, $newId);
            }

            $inserted++;
        }

        return ['inserted' => $inserted, 'idMap' => $idMap, 'warnings' => $warnings];
    }

    /**
     * Inserts a project with explicit ID (for replacement) or auto-increment (for new).
     *
     * @return int The local project ID
     */
    public function insertProject(
        array $record,
        array $insertColumns,
        array $productionColumnInfo,
        array $tempColumns,
        ?int $localProjectId
    ): int {
        $excludeColumns = EntityConfig::getExcludedColumns('project');
        $overrideColumns = EntityConfig::getOverrideColumns('project');
        $tempColumnSet = array_flip($tempColumns);

        $columns = $insertColumns;
        $values = [];

        // For replacement: include 'id' column with the local project ID
        if ($localProjectId !== null) {
            array_unshift($columns, 'id');
            $values[] = $localProjectId;
        }

        foreach ($insertColumns as $column) {
            if (in_array($column, $excludeColumns, true)) {
                $values[] = null;
                continue;
            }

            if (in_array($column, $overrideColumns, true)) {
                $values[] = $this->userId;
                continue;
            }

            if (isset($tempColumnSet[$column]) && array_key_exists($column, $record)) {
                // Handle label conflict
                if ($column === 'label' && $record[$column] !== null) {
                    if ($this->hasLabelConflict($record[$column], $localProjectId)) {
                        $values[] = null;
                        continue;
                    }
                }
                $values[] = $record[$column];
                continue;
            }

            $info = $productionColumnInfo[$column] ?? [];
            $values[] = SchemaInspector::getPhpFallbackValue($info);
        }

        $this->db->createCommand()->insert(
            "`{$this->productionSchema}`.`project`",
            array_combine($columns, $values)
        )->execute();

        if ($localProjectId !== null) {
            return $localProjectId;
        }

        return (int) $this->db->getLastInsertID();
    }

    /**
     * Updates template_body placeholder IDs for all loaded templates.
     */
    public function updateTemplatePlaceholders(PlaceholderRemapper $remapper, array $templateIdMap): void
    {
        $schema = $this->productionSchema;

        foreach ($templateIdMap as $dumpId => $localId) {
            $templateBody = $this->db->createCommand(
                "SELECT template_body FROM `{$schema}`.`prompt_template` WHERE id = :id",
                [':id' => $localId]
            )->queryScalar();

            if (!$templateBody) {
                continue;
            }

            $remapped = $remapper->remap($templateBody);
            if ($remapped !== $templateBody) {
                $this->db->createCommand()->update(
                    "`{$schema}`.`prompt_template`",
                    ['template_body' => $remapped],
                    ['id' => $localId]
                )->execute();
            }
        }
    }

    public function addIdMapping(string $entity, int $sourceId, int $destId): void
    {
        $this->idMappings[$entity][$sourceId] = $destId;
    }

    public function getMappedId(string $entity, int $sourceId): ?int
    {
        return $this->idMappings[$entity][$sourceId] ?? null;
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function getIdMappings(): array
    {
        return $this->idMappings;
    }

    public function clearIdMappings(): void
    {
        $this->idMappings = [];
    }

    /**
     * Checks if a label is already in use by another local project of this user.
     */
    private function hasLabelConflict(string $label, ?int $excludeProjectId): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->productionSchema}`.`project`
                WHERE label = :label AND user_id = :userId AND deleted_at IS NULL";
        $params = [':label' => $label, ':userId' => $this->userId];

        if ($excludeProjectId !== null) {
            $sql .= ' AND id != :excludeId';
            $params[':excludeId'] = $excludeProjectId;
        }

        return (int) $this->db->createCommand($sql, $params)->queryScalar() > 0;
    }

}
