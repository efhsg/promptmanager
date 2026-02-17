<?php

namespace app\services\projectload;

use Exception;
use RuntimeException;
use Yii;
use yii\db\Connection;

/**
 * Orchestrates loading projects from a MySQL dump file.
 *
 * Coordinates dump import, schema validation, entity loading, and placeholder remapping.
 */
class ProjectLoadService
{
    private Connection $db;
    private DumpImporter $dumpImporter;
    private SchemaInspector $schemaInspector;
    private string $productionSchema;

    public function __construct(
        Connection $db,
        DumpImporter $dumpImporter,
        SchemaInspector $schemaInspector
    ) {
        $this->db = $db;
        $this->dumpImporter = $dumpImporter;
        $this->schemaInspector = $schemaInspector;
        $this->productionSchema = $schemaInspector->getProductionSchema();
    }

    /**
     * Lists projects available in a dump file.
     *
     * @return array{projects: array[], entityCounts: array<int, array<string, int|string>>}
     * @throws RuntimeException
     */
    public function listProjects(string $filePath): array
    {
        $filePath = $this->dumpImporter->validateDumpFile($filePath);
        $tempSchema = $this->dumpImporter->createTempSchema();

        try {
            $this->dumpImporter->importDump($filePath, $tempSchema);

            if (!$this->schemaInspector->tableExists($tempSchema, 'project')) {
                throw new RuntimeException('Dump bevat geen "project" tabel');
            }

            $projects = $this->db->createCommand(
                "SELECT * FROM `{$tempSchema}`.`project` ORDER BY id"
            )->queryAll();

            // Dynamic column detection: check which related tables exist
            $listColumns = EntityConfig::getListColumns();
            $tableNames = [];
            foreach ($listColumns as $entity => $label) {
                $config = EntityConfig::getEntities()[$entity];
                $tableNames[] = $config['table'];
            }
            $existingTables = $this->schemaInspector->getExistingTables($tempSchema, $tableNames);

            // Count related entities per project
            $entityCounts = [];
            foreach ($projects as $project) {
                $projectId = (int) $project['id'];
                $counts = [];

                foreach ($listColumns as $entity => $label) {
                    $config = EntityConfig::getEntities()[$entity];
                    $table = $config['table'];

                    if (!$existingTables[$table]) {
                        $counts[$entity] = '-';
                        continue;
                    }

                    $parentColumn = $config['parentKey'];
                    if ($parentColumn === 'project_id') {
                        $counts[$entity] = (int) $this->db->createCommand(
                            "SELECT COUNT(*) FROM `{$tempSchema}`.`{$table}` WHERE project_id = :id",
                            [':id' => $projectId]
                        )->queryScalar();
                    } elseif ($entity === 'prompt_instance') {
                        $counts[$entity] = (int) $this->db->createCommand(
                            "SELECT COUNT(*) FROM `{$tempSchema}`.`{$table}`
                             WHERE template_id IN (SELECT id FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id)",
                            [':id' => $projectId]
                        )->queryScalar();
                    } else {
                        $counts[$entity] = 0;
                    }
                }

                $entityCounts[$projectId] = $counts;
            }

            return [
                'projects' => $projects,
                'entityCounts' => $entityCounts,
            ];
        } finally {
            $this->dumpImporter->dropSchema($tempSchema);
        }
    }

    /**
     * Loads projects from a dump file.
     *
     * @param int[] $projectIds
     * @param int[] $localProjectIds
     * @throws RuntimeException
     */
    public function load(
        string $filePath,
        array $projectIds,
        int $userId = 1,
        bool $dryRun = false,
        bool $includeGlobalFields = false,
        array $localProjectIds = []
    ): LoadReport {
        $filePath = $this->dumpImporter->validateDumpFile($filePath);
        $this->validateInput($projectIds, $userId, $localProjectIds);

        $report = new LoadReport();
        $tempSchema = $this->dumpImporter->createTempSchema();

        try {
            $this->dumpImporter->importDump($filePath, $tempSchema);

            $productionSchema = $this->productionSchema;
            $entityLoader = new EntityLoader(
                $this->db,
                $this->schemaInspector,
                $tempSchema,
                $productionSchema,
                $userId
            );

            $placeholderRemapper = new PlaceholderRemapper($this->db, $tempSchema, $userId);

            // Build local-project-ids mapping if provided
            $explicitMapping = [];
            if (!empty($localProjectIds)) {
                foreach ($projectIds as $i => $dumpId) {
                    $explicitMapping[$dumpId] = $localProjectIds[$i];
                }
            }

            foreach ($projectIds as $dumpProjectId) {
                $this->loadSingleProject(
                    $entityLoader,
                    $placeholderRemapper,
                    $report,
                    $tempSchema,
                    $productionSchema,
                    $dumpProjectId,
                    $userId,
                    $dryRun,
                    $includeGlobalFields,
                    $explicitMapping
                );
            }

            return $report;
        } finally {
            $this->dumpImporter->dropSchema($tempSchema);
        }
    }

    /**
     * Cleans up orphaned temp schemas.
     *
     * @return array<string, ?string>
     */
    public function cleanup(): array
    {
        return $this->dumpImporter->cleanupOrphanedSchemas();
    }

    private function loadSingleProject(
        EntityLoader $entityLoader,
        PlaceholderRemapper $placeholderRemapper,
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        int $userId,
        bool $dryRun,
        bool $includeGlobalFields,
        array $explicitMapping
    ): void {
        // Fetch project from dump
        $dumpProject = $entityLoader->fetchProject($dumpProjectId);
        if ($dumpProject === null) {
            $report->initProject($dumpProjectId, "(niet gevonden)");
            $report->setProjectStatus($dumpProjectId, 'skipped');
            $report->addWarning($dumpProjectId, "Project ID {$dumpProjectId} niet gevonden in dump");
            return;
        }

        $projectName = $dumpProject['name'];
        $report->initProject($dumpProjectId, $projectName);

        // Check soft-delete
        if ($dumpProject['deleted_at'] !== null) {
            $report->setProjectStatus($dumpProjectId, 'skipped');
            $report->addWarning($dumpProjectId, "Project \"{$projectName}\" is soft-deleted in dump (deleted_at = {$dumpProject['deleted_at']})");
            return;
        }

        // Determine local match
        $localMatch = $this->findLocalMatch($dumpProjectId, $projectName, $userId, $explicitMapping, $report);
        if ($localMatch === false) {
            // Error already set in report
            return;
        }

        $localProjectId = $localMatch;
        $isReplacement = $localProjectId !== null;
        $report->setProjectLocalMatch($dumpProjectId, $localProjectId, $isReplacement);

        if ($dryRun) {
            $this->generateDryRunReport(
                $entityLoader,
                $report,
                $tempSchema,
                $productionSchema,
                $dumpProjectId,
                $dumpProject,
                $localProjectId,
                $isReplacement,
                $userId,
                $includeGlobalFields
            );
            return;
        }

        // Begin transaction per project
        $transaction = $this->db->beginTransaction();
        try {
            // Delete existing project if replacing
            if ($isReplacement) {
                $entityLoader->deleteLocalProject($localProjectId);
            }

            // Load project and all children
            $this->loadProjectData(
                $entityLoader,
                $placeholderRemapper,
                $report,
                $tempSchema,
                $productionSchema,
                $dumpProjectId,
                $dumpProject,
                $localProjectId,
                $userId,
                $includeGlobalFields
            );

            $transaction->commit();
            $report->setProjectStatus($dumpProjectId, 'success');
        } catch (Exception $e) {
            $transaction->rollBack();
            $report->setProjectError($dumpProjectId, $e->getMessage());
            Yii::error("Project load failed for dump ID {$dumpProjectId}: " . $e->getMessage());
        }
    }

    /**
     * @return int|null|false null=new project, int=local ID, false=error
     */
    private function findLocalMatch(
        int $dumpProjectId,
        string $projectName,
        int $userId,
        array $explicitMapping,
        LoadReport $report
    ): int|false|null {
        // Explicit mapping via --local-project-ids
        if (isset($explicitMapping[$dumpProjectId])) {
            $localId = $explicitMapping[$dumpProjectId];
            $schema = $this->productionSchema;

            $local = $this->db->createCommand(
                "SELECT id, name, user_id, deleted_at FROM `{$schema}`.`project` WHERE id = :id",
                [':id' => $localId]
            )->queryOne();

            if (!$local) {
                $report->setProjectError($dumpProjectId, "Lokaal project ID {$localId} niet gevonden");
                return false;
            }
            if ((int) $local['user_id'] !== $userId) {
                $report->setProjectError($dumpProjectId, "Lokaal project ID {$localId} behoort niet tot user {$userId}");
                return false;
            }
            if ($local['deleted_at'] !== null) {
                $report->addWarning($dumpProjectId, "Lokaal project is soft-deleted (deleted_at = {$local['deleted_at']}). Na load wordt het opnieuw actief.");
            }

            return $localId;
        }

        // Name + user_id matching (excluding soft-deleted)
        $schema = $this->productionSchema;
        $matches = $this->db->createCommand(
            "SELECT id, name, created_at FROM `{$schema}`.`project`
             WHERE name = :name AND user_id = :userId AND deleted_at IS NULL",
            [':name' => $projectName, ':userId' => $userId]
        )->queryAll();

        if (count($matches) === 0) {
            return null; // New project
        }

        if (count($matches) === 1) {
            return (int) $matches[0]['id'];
        }

        // Multiple matches — error
        $details = array_map(
            fn(array $m) => "  ID {$m['id']}: \"{$m['name']}\" (aangemaakt {$m['created_at']})",
            $matches
        );
        $report->setProjectError(
            $dumpProjectId,
            "Meerdere lokale projecten gevonden met naam \"{$projectName}\" voor user {$userId}:\n"
            . implode("\n", $details) . "\n"
            . "Gebruik --local-project-ids om expliciet te matchen."
        );
        return false;
    }

    private function loadProjectData(
        EntityLoader $entityLoader,
        PlaceholderRemapper $placeholderRemapper,
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        array $dumpProject,
        ?int $localProjectId,
        int $userId,
        bool $includeGlobalFields
    ): void {
        $entities = EntityConfig::getEntities();
        $autoIncrementEntities = EntityConfig::getAutoIncrementEntities();

        // Get production column info for all entity tables
        $productionColumnInfo = [];
        $insertColumnsByEntity = [];
        $tempColumnsByEntity = [];

        foreach (EntityConfig::getInsertOrder() as $entity) {
            $config = $entities[$entity];
            $table = $config['table'];
            $excludes = EntityConfig::getExcludedColumns($entity);
            $hasAutoIncrement = in_array($entity, $autoIncrementEntities, true);

            $productionColumnInfo[$entity] = $this->schemaInspector->getColumnInfo($productionSchema, $table);
            $insertColumnsByEntity[$entity] = $this->schemaInspector->getInsertColumns(
                $productionSchema,
                $table,
                $excludes,
                $hasAutoIncrement
            );

            if ($this->schemaInspector->tableExists($tempSchema, $table)) {
                $tempColumnsByEntity[$entity] = $this->schemaInspector->getColumnNames($tempSchema, $table);
            } else {
                $tempColumnsByEntity[$entity] = [];
            }
        }

        // 1. Insert project
        $newProjectId = $entityLoader->insertProject(
            $dumpProject,
            $insertColumnsByEntity['project'],
            $productionColumnInfo['project'],
            $tempColumnsByEntity['project'],
            $localProjectId
        );
        $entityLoader->addIdMapping('project', $dumpProjectId, $newProjectId);
        $report->addInserted($dumpProjectId, 'project');
        $report->addIdMapping('project', $dumpProjectId, $newProjectId);

        // 2. Load fields (project-bound)
        $dumpFields = $entityLoader->fetchFromTemp('field', 'project_id', $dumpProjectId);
        $fieldResult = $entityLoader->loadEntityRecords(
            'field',
            'field',
            $dumpFields,
            $insertColumnsByEntity['field'],
            $productionColumnInfo['field'],
            $tempColumnsByEntity['field'],
            EntityConfig::getExcludedColumns('field'),
            EntityConfig::getOverrideColumns('field'),
            $entities['field']['foreignKeys'],
            true
        );
        $report->addInserted($dumpProjectId, 'field', $fieldResult['inserted']);
        foreach ($fieldResult['idMap'] as $dumpId => $localId) {
            $report->addIdMapping('field', $dumpId, $localId);
        }
        $projectFieldMap = $fieldResult['idMap'];

        // 3. Handle global fields if requested
        $globalFieldMap = [];
        if ($includeGlobalFields) {
            $globalFieldMap = $this->loadGlobalFields(
                $entityLoader,
                $report,
                $tempSchema,
                $productionSchema,
                $dumpProjectId,
                $dumpProject,
                $insertColumnsByEntity,
                $productionColumnInfo,
                $tempColumnsByEntity,
                $userId
            );
        }

        // 4. Load field_options
        $dumpFieldIds = array_keys($projectFieldMap);
        if (!empty($dumpFieldIds)) {
            $dumpFieldOptions = $entityLoader->fetchFromTemp('field_option', 'field_id', $dumpFieldIds);
            $foResult = $entityLoader->loadEntityRecords(
                'field_option',
                'field_option',
                $dumpFieldOptions,
                $insertColumnsByEntity['field_option'],
                $productionColumnInfo['field_option'],
                $tempColumnsByEntity['field_option'],
                [],
                [],
                $entities['field_option']['foreignKeys'],
                true
            );
            $report->addInserted($dumpProjectId, 'field_option', $foResult['inserted']);
        }

        // Also load field_options for newly created global fields
        $newGlobalFieldDumpIds = array_keys(array_filter(
            $globalFieldMap,
            fn(int $localId, int $dumpId) => !$this->isExistingGlobalField($dumpId, $globalFieldMap),
            ARRAY_FILTER_USE_BOTH
        ));
        if (!empty($newGlobalFieldDumpIds)) {
            $globalFieldOptions = $entityLoader->fetchFromTemp('field_option', 'field_id', $newGlobalFieldDumpIds);
            $gfoResult = $entityLoader->loadEntityRecords(
                'field_option',
                'field_option',
                $globalFieldOptions,
                $insertColumnsByEntity['field_option'],
                $productionColumnInfo['field_option'],
                $tempColumnsByEntity['field_option'],
                [],
                [],
                $entities['field_option']['foreignKeys'],
                true
            );
            $report->addInserted($dumpProjectId, 'field_option', $gfoResult['inserted']);
        }

        // 5. Load contexts
        $dumpContexts = $entityLoader->fetchFromTemp('context', 'project_id', $dumpProjectId);
        $ctxResult = $entityLoader->loadEntityRecords(
            'context',
            'context',
            $dumpContexts,
            $insertColumnsByEntity['context'],
            $productionColumnInfo['context'],
            $tempColumnsByEntity['context'],
            [],
            [],
            $entities['context']['foreignKeys'],
            true
        );
        $report->addInserted($dumpProjectId, 'context', $ctxResult['inserted']);

        // 6. Load prompt_templates
        $dumpTemplates = $entityLoader->fetchFromTemp('prompt_template', 'project_id', $dumpProjectId);
        $tplResult = $entityLoader->loadEntityRecords(
            'prompt_template',
            'prompt_template',
            $dumpTemplates,
            $insertColumnsByEntity['prompt_template'],
            $productionColumnInfo['prompt_template'],
            $tempColumnsByEntity['prompt_template'],
            [],
            [],
            $entities['prompt_template']['foreignKeys'],
            true
        );
        $report->addInserted($dumpProjectId, 'prompt_template', $tplResult['inserted']);
        $templateIdMap = $tplResult['idMap'];

        // 7. Load template_fields
        $dumpTemplateIds = array_keys($templateIdMap);
        if (!empty($dumpTemplateIds)) {
            $dumpTemplateFields = $entityLoader->fetchFromTemp('template_field', 'template_id', $dumpTemplateIds);

            // For template_field: need to remap field_id via placeholder remapper
            $placeholderRemapper->setProjectFieldMap($projectFieldMap);
            $placeholderRemapper->setGlobalFieldMap($globalFieldMap);

            $tfInserted = 0;
            foreach ($dumpTemplateFields as $tf) {
                $remappedTemplateId = $entityLoader->getMappedId('prompt_template', (int) $tf['template_id']);
                $remappedFieldId = $placeholderRemapper->remapFieldId((int) $tf['field_id']);

                if ($remappedTemplateId === null || $remappedFieldId === null) {
                    $report->addWarning($dumpProjectId, "template_field overgeslagen: template_id={$tf['template_id']}, field_id={$tf['field_id']} — niet gevonden");
                    continue;
                }

                $tfColumns = $insertColumnsByEntity['template_field'];
                $tfValues = [];
                foreach ($tfColumns as $col) {
                    if ($col === 'template_id') {
                        $tfValues[] = $remappedTemplateId;
                    } elseif ($col === 'field_id') {
                        $tfValues[] = $remappedFieldId;
                    } else {
                        $tfValues[] = $tf[$col] ?? null;
                    }
                }

                $entityLoader->insertRecord('template_field', $tfColumns, $tfValues);
                $tfInserted++;
            }
            $report->addInserted($dumpProjectId, 'template_field', $tfInserted);
        }

        // 8. Load notes
        $dumpNotes = $entityLoader->fetchFromTemp('note', 'project_id', $dumpProjectId);
        $noteResult = $entityLoader->loadEntityRecords(
            'note',
            'note',
            $dumpNotes,
            $insertColumnsByEntity['note'],
            $productionColumnInfo['note'],
            $tempColumnsByEntity['note'],
            [],
            EntityConfig::getOverrideColumns('note'),
            $entities['note']['foreignKeys'],
            true
        );
        $report->addInserted($dumpProjectId, 'note', $noteResult['inserted']);

        // 9. Load prompt_instances
        if (!empty($dumpTemplateIds)) {
            $dumpInstances = $entityLoader->fetchFromTemp('prompt_instance', 'template_id', $dumpTemplateIds);
            $piResult = $entityLoader->loadEntityRecords(
                'prompt_instance',
                'prompt_instance',
                $dumpInstances,
                $insertColumnsByEntity['prompt_instance'],
                $productionColumnInfo['prompt_instance'],
                $tempColumnsByEntity['prompt_instance'],
                [],
                [],
                $entities['prompt_instance']['foreignKeys'],
                true
            );
            $report->addInserted($dumpProjectId, 'prompt_instance', $piResult['inserted']);
        }

        // 10. Load project_linked_project
        $this->loadProjectLinks(
            $entityLoader,
            $report,
            $tempSchema,
            $productionSchema,
            $dumpProjectId,
            $insertColumnsByEntity,
            $productionColumnInfo,
            $tempColumnsByEntity
        );

        // 11. Remap placeholders in template_body
        if (!empty($templateIdMap)) {
            $placeholderRemapper->setProjectFieldMap($projectFieldMap);
            $placeholderRemapper->setGlobalFieldMap($globalFieldMap);
            $placeholderRemapper->clearWarnings();

            $entityLoader->updateTemplatePlaceholders($placeholderRemapper, $templateIdMap);

            foreach ($placeholderRemapper->getWarnings() as $warning) {
                $report->addWarning($dumpProjectId, $warning);
            }
        }
    }

    /**
     * Loads global fields referenced by this project's templates.
     *
     * @return array<int, int> dumpFieldId => localFieldId
     */
    private function loadGlobalFields(
        EntityLoader $entityLoader,
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        array $dumpProject,
        array $insertColumnsByEntity,
        array $productionColumnInfo,
        array $tempColumnsByEntity,
        int $userId
    ): array {
        // Find global field IDs referenced in template bodies (GEN:{{id}} placeholders)
        $dumpTemplates = $this->db->createCommand(
            "SELECT template_body FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryColumn();

        $referencedGlobalIds = [];
        foreach ($dumpTemplates as $body) {
            if (preg_match_all('/GEN:\{\{(\d+)\}\}/', $body, $matches)) {
                foreach ($matches[1] as $id) {
                    $referencedGlobalIds[(int) $id] = true;
                }
            }
        }

        // Also find global field IDs referenced in template_field records
        $dumpTemplateIds = $this->db->createCommand(
            "SELECT id FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryColumn();

        if (!empty($dumpTemplateIds) && $this->schemaInspector->tableExists($tempSchema, 'template_field')) {
            $tfPlaceholders = [];
            $tfParams = [];
            foreach (array_values($dumpTemplateIds) as $i => $tid) {
                $key = ":tid{$i}";
                $tfPlaceholders[] = $key;
                $tfParams[$key] = $tid;
            }

            $tfFieldIds = $this->db->createCommand(
                "SELECT DISTINCT tf.field_id FROM `{$tempSchema}`.`template_field` tf
                 INNER JOIN `{$tempSchema}`.`field` f ON f.id = tf.field_id
                 WHERE tf.template_id IN (" . implode(',', $tfPlaceholders) . ")
                 AND f.project_id IS NULL",
                $tfParams
            )->queryColumn();

            foreach ($tfFieldIds as $id) {
                $referencedGlobalIds[(int) $id] = true;
            }
        }

        if (empty($referencedGlobalIds)) {
            return [];
        }

        $globalFieldIds = array_keys($referencedGlobalIds);
        $params = [];
        $placeholders = [];
        foreach (array_values($globalFieldIds) as $i => $id) {
            $key = ":gf{$i}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $dumpGlobalFields = $this->db->createCommand(
            "SELECT * FROM `{$tempSchema}`.`field`
             WHERE project_id IS NULL AND id IN (" . implode(',', $placeholders) . ")",
            $params
        )->queryAll();

        $globalFieldMap = [];

        foreach ($dumpGlobalFields as $dumpField) {
            $dumpId = (int) $dumpField['id'];
            $fieldName = $dumpField['name'];

            // Check if exists locally
            $localField = $this->db->createCommand(
                "SELECT id, type FROM `{$productionSchema}`.`field`
                 WHERE name = :name AND project_id IS NULL AND user_id = :userId",
                [':name' => $fieldName, ':userId' => $userId]
            )->queryOne();

            if ($localField) {
                // Use existing local field
                $globalFieldMap[$dumpId] = (int) $localField['id'];
                $entityLoader->addIdMapping('field', $dumpId, (int) $localField['id']);

                // Warn on type mismatch
                if ($localField['type'] !== $dumpField['type']) {
                    $report->addWarning(
                        $dumpProjectId,
                        "Globaal veld \"{$fieldName}\" is lokaal type \"{$localField['type']}\" "
                        . "maar type \"{$dumpField['type']}\" in dump"
                    );
                }
            } else {
                // Create new global field
                $entities = EntityConfig::getEntities();
                // For new global fields: project_id should remain NULL, not remapped
                $gfForeignKeys = [];
                $gfRecord = $dumpField;

                $insertCols = $insertColumnsByEntity['field'];
                $colInfo = $productionColumnInfo['field'];
                $tempCols = $tempColumnsByEntity['field'];
                $overrides = EntityConfig::getOverrideColumns('field');
                $tempColumnSet = array_flip($tempCols);

                $values = [];
                foreach ($insertCols as $column) {
                    if (in_array($column, $overrides, true)) {
                        $values[] = $userId;
                    } elseif ($column === 'project_id') {
                        $values[] = null; // Global field
                    } elseif ($column === 'label') {
                        // Check label conflict for global fields
                        $label = $gfRecord[$column] ?? null;
                        if ($label !== null) {
                            $conflict = (int) $this->db->createCommand(
                                "SELECT COUNT(*) FROM `{$productionSchema}`.`field`
                                 WHERE label = :label AND project_id IS NULL AND user_id = :userId",
                                [':label' => $label, ':userId' => $userId]
                            )->queryScalar();
                            $values[] = $conflict > 0 ? null : $label;
                            if ($conflict > 0) {
                                $report->addWarning($dumpProjectId, "Globaal veld \"{$fieldName}\": label \"{$label}\" al in gebruik — label niet overgenomen");
                            }
                        } else {
                            $values[] = null;
                        }
                    } elseif (isset($tempColumnSet[$column]) && array_key_exists($column, $gfRecord)) {
                        $values[] = $gfRecord[$column];
                    } else {
                        $info = $colInfo[$column] ?? [];
                        $values[] = SchemaInspector::getPhpFallbackValue($info);
                    }
                }

                $newId = $entityLoader->insertRecord('field', $insertCols, $values);
                $globalFieldMap[$dumpId] = $newId;
                $entityLoader->addIdMapping('field', $dumpId, $newId);
                $report->addInserted($dumpProjectId, 'field', 1);
            }
        }

        return $globalFieldMap;
    }

    private function loadProjectLinks(
        EntityLoader $entityLoader,
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        array $insertColumnsByEntity,
        array $productionColumnInfo,
        array $tempColumnsByEntity
    ): void {
        $dumpLinks = $entityLoader->fetchFromTemp('project_linked_project', 'project_id', $dumpProjectId);
        $inserted = 0;

        foreach ($dumpLinks as $link) {
            $linkedDumpId = (int) $link['linked_project_id'];

            // Check if linked project was loaded in this run
            $localLinkedId = $entityLoader->getMappedId('project', $linkedDumpId);

            // If not loaded in this run, check if exists locally
            if ($localLinkedId === null) {
                $exists = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM `{$productionSchema}`.`project` WHERE id = :id AND deleted_at IS NULL",
                    [':id' => $linkedDumpId]
                )->queryScalar();

                if ($exists > 0) {
                    $localLinkedId = $linkedDumpId;
                }
            }

            if ($localLinkedId === null) {
                $report->addWarning(
                    $dumpProjectId,
                    "Gelinkt project (dump ID: {$linkedDumpId}) bestaat niet lokaal — link overgeslagen"
                );
                continue;
            }

            $localProjectId = $entityLoader->getMappedId('project', $dumpProjectId);
            $columns = $insertColumnsByEntity['project_linked_project'];
            $tempCols = $tempColumnsByEntity['project_linked_project'] ?? [];
            $tempColumnSet = array_flip($tempCols);
            $values = [];

            foreach ($columns as $col) {
                if ($col === 'project_id') {
                    $values[] = $localProjectId;
                } elseif ($col === 'linked_project_id') {
                    $values[] = $localLinkedId;
                } elseif (isset($tempColumnSet[$col]) && array_key_exists($col, $link)) {
                    $values[] = $link[$col];
                } else {
                    $info = $productionColumnInfo['project_linked_project'][$col] ?? [];
                    $values[] = SchemaInspector::getPhpFallbackValue($info);
                }
            }

            $entityLoader->insertRecord('project_linked_project', $columns, $values);
            $inserted++;
        }

        $report->addInserted($dumpProjectId, 'project_linked_project', $inserted);
    }

    private function generateDryRunReport(
        EntityLoader $entityLoader,
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        array $dumpProject,
        ?int $localProjectId,
        bool $isReplacement,
        int $userId,
        bool $includeGlobalFields
    ): void {
        $report->setProjectStatus($dumpProjectId, 'dry-run');

        // Report what would be deleted
        if ($isReplacement) {
            $localCounts = $entityLoader->countLocalEntities($localProjectId);
            foreach ($localCounts as $entity => $count) {
                if ($count > 0) {
                    $report->addDeleted($dumpProjectId, $entity, $count);
                }
            }
        }

        // Report what would be loaded from dump
        $report->addInserted($dumpProjectId, 'project', 1);

        $listColumns = EntityConfig::getListColumns();
        foreach ($listColumns as $entity => $label) {
            $config = EntityConfig::getEntities()[$entity];
            $table = $config['table'];

            if (!$this->schemaInspector->tableExists($tempSchema, $table)) {
                continue;
            }

            $parentColumn = $config['parentKey'];
            if ($parentColumn === 'project_id') {
                $count = $entityLoader->countInTemp($table, 'project_id', $dumpProjectId);
            } elseif ($entity === 'prompt_instance') {
                $templateIds = $this->db->createCommand(
                    "SELECT id FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id",
                    [':id' => $dumpProjectId]
                )->queryColumn();
                $count = !empty($templateIds)
                    ? $entityLoader->countInTemp($table, 'template_id', array_map('intval', $templateIds))
                    : 0;
            } elseif ($entity === 'field') {
                $count = $entityLoader->countInTemp($table, 'project_id', $dumpProjectId);
            } else {
                continue;
            }

            if ($count > 0) {
                $report->addInserted($dumpProjectId, $entity, $count);
            }
        }

        // Count field_options and template_fields
        $fieldIds = $this->db->createCommand(
            "SELECT id FROM `{$tempSchema}`.`field` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryColumn();
        if (!empty($fieldIds)) {
            $foCount = $entityLoader->countInTemp('field_option', 'field_id', array_map('intval', $fieldIds));
            if ($foCount > 0) {
                $report->addInserted($dumpProjectId, 'field_option', $foCount);
            }
        }

        $templateIds = $this->db->createCommand(
            "SELECT id FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryColumn();
        if (!empty($templateIds)) {
            $tfCount = $entityLoader->countInTemp('template_field', 'template_id', array_map('intval', $templateIds));
            if ($tfCount > 0) {
                $report->addInserted($dumpProjectId, 'template_field', $tfCount);
            }
        }

        // Dry-run warnings
        $report->addWarning($dumpProjectId, "root_directory, ai_options, ai_context worden niet geladen (machine-specifiek) — configureer na het laden.");

        // Check for global field references without --include-global-fields
        if (!$includeGlobalFields) {
            $this->checkGlobalFieldWarnings($report, $tempSchema, $productionSchema, $dumpProjectId, $userId);
        }

        // Check linked project availability
        $this->checkLinkedProjectWarnings($report, $tempSchema, $productionSchema, $dumpProjectId, $entityLoader);
    }

    private function checkGlobalFieldWarnings(
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        int $userId
    ): void {
        $templates = $this->db->createCommand(
            "SELECT name, template_body FROM `{$tempSchema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryAll();

        foreach ($templates as $tpl) {
            if (!$tpl['template_body'] || !preg_match_all('/GEN:\{\{(\d+)\}\}/', $tpl['template_body'], $matches)) {
                continue;
            }

            foreach ($matches[1] as $globalFieldId) {
                $fieldName = $this->db->createCommand(
                    "SELECT name FROM `{$tempSchema}`.`field` WHERE id = :id AND project_id IS NULL",
                    [':id' => $globalFieldId]
                )->queryScalar();

                if ($fieldName === false) {
                    continue;
                }

                $localExists = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM `{$productionSchema}`.`field`
                     WHERE name = :name AND project_id IS NULL AND user_id = :userId",
                    [':name' => $fieldName, ':userId' => $userId]
                )->queryScalar();

                if ($localExists === 0) {
                    $report->addWarning(
                        $dumpProjectId,
                        "Template \"{$tpl['name']}\" refereert globaal veld \"{$fieldName}\" (GEN:{{{$globalFieldId}}}) "
                        . "dat lokaal niet bestaat. Gebruik --include-global-fields om mee te laden."
                    );
                }
            }
        }
    }

    private function checkLinkedProjectWarnings(
        LoadReport $report,
        string $tempSchema,
        string $productionSchema,
        int $dumpProjectId,
        EntityLoader $entityLoader
    ): void {
        if (!$this->schemaInspector->tableExists($tempSchema, 'project_linked_project')) {
            return;
        }

        $links = $this->db->createCommand(
            "SELECT linked_project_id FROM `{$tempSchema}`.`project_linked_project` WHERE project_id = :id",
            [':id' => $dumpProjectId]
        )->queryAll();

        foreach ($links as $link) {
            $linkedId = (int) $link['linked_project_id'];
            $localExists = $entityLoader->getMappedId('project', $linkedId);

            if ($localExists === null) {
                $exists = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM `{$productionSchema}`.`project` WHERE id = :id AND deleted_at IS NULL",
                    [':id' => $linkedId]
                )->queryScalar();

                if ($exists === 0) {
                    $linkedName = $this->db->createCommand(
                        "SELECT name FROM `{$tempSchema}`.`project` WHERE id = :id",
                        [':id' => $linkedId]
                    )->queryScalar() ?: "(onbekend)";

                    $report->addWarning(
                        $dumpProjectId,
                        "Gelinkt project \"{$linkedName}\" (dump ID: {$linkedId}) bestaat niet lokaal — link wordt overgeslagen"
                    );
                }
            }
        }
    }

    private function validateInput(array $projectIds, int $userId, array $localProjectIds): void
    {
        if (empty($projectIds)) {
            throw new RuntimeException('Geen project-IDs opgegeven');
        }

        foreach ($projectIds as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new RuntimeException("Ongeldige project-ID: {$id}");
            }
        }

        if ($userId <= 0) {
            throw new RuntimeException("Ongeldige user-ID: {$userId}");
        }

        // Validate user exists
        $schema = $this->productionSchema;
        $userExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`user` WHERE id = :id",
            [':id' => $userId]
        )->queryScalar();
        if ($userExists === 0) {
            throw new RuntimeException("User met ID {$userId} niet gevonden");
        }

        if (!empty($localProjectIds)) {
            if (count($localProjectIds) !== count($projectIds)) {
                throw new RuntimeException(
                    "Aantal --local-project-ids (" . count($localProjectIds) . ") "
                    . "moet gelijk zijn aan aantal --project-ids (" . count($projectIds) . ")"
                );
            }
            foreach ($localProjectIds as $id) {
                if (!is_int($id) || $id <= 0) {
                    throw new RuntimeException("Ongeldige lokale project-ID: {$id}");
                }
            }
        }
    }

    /**
     * Tracks which global field dump IDs were newly created vs matched to existing.
     * This is a simplified check — if the dump ID maps to a different local ID, it was likely a new insert.
     */
    private function isExistingGlobalField(int $dumpId, array $globalFieldMap): bool
    {
        // If the mapped local ID is different from the dump ID, it could be either new or existing.
        // We need to check production to be sure.
        $localId = $globalFieldMap[$dumpId] ?? null;
        if ($localId === null) {
            return false;
        }

        $schema = $this->productionSchema;
        return (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$schema}`.`field`
             WHERE id = :id AND project_id IS NULL",
            [':id' => $localId]
        )->queryScalar() > 0;
    }

}
