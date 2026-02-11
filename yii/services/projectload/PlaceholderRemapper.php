<?php

namespace app\services\projectload;

use yii\db\Connection;

/**
 * Remaps placeholder IDs in template_body Quill Delta JSON.
 *
 * Handles PRJ:{{id}}, GEN:{{id}}, and EXT:{{id}} placeholders, mapping
 * source field IDs to local field IDs after load.
 *
 * Follows the established pattern from PromptTemplateService::convertPlaceholdersToIds():
 * parse delta JSON → iterate ops → preg_replace_callback → re-encode.
 */
class PlaceholderRemapper
{
    private Connection $db;
    private string $tempSchema;
    private int $userId;

    /** @var array<int, int> dumpFieldId => localFieldId for project fields */
    private array $projectFieldMap = [];

    /** @var array<int, int> dumpFieldId => localFieldId for global fields */
    private array $globalFieldMap = [];

    /** @var string[] */
    private array $warnings = [];

    private string $productionSchema;

    public function __construct(Connection $db, string $tempSchema, int $userId)
    {
        $this->db = $db;
        $this->tempSchema = $tempSchema;
        $this->userId = $userId;
        $this->productionSchema = $db->createCommand('SELECT DATABASE()')->queryScalar();
    }

    /**
     * @param array<int, int> $projectFieldMap dumpFieldId => localFieldId
     */
    public function setProjectFieldMap(array $projectFieldMap): void
    {
        $this->projectFieldMap = $projectFieldMap;
    }

    /**
     * @param array<int, int> $globalFieldMap dumpFieldId => localFieldId
     */
    public function setGlobalFieldMap(array $globalFieldMap): void
    {
        $this->globalFieldMap = $globalFieldMap;
    }

    /**
     * Remaps placeholder IDs in a template_body Quill Delta JSON string.
     */
    public function remap(string $templateBody): string
    {
        $delta = json_decode($templateBody, true);
        if (!$delta || !isset($delta['ops'])) {
            return $templateBody;
        }

        foreach ($delta['ops'] as &$op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $op['insert'] = preg_replace_callback(
                    '/(GEN|PRJ|EXT):\{\{(\d+)\}\}/',
                    function (array $matches): string {
                        $type = $matches[1];
                        $dumpId = (int) $matches[2];

                        return match ($type) {
                            'PRJ' => $this->remapPrj($dumpId, $matches[0]),
                            'GEN' => $this->remapGen($dumpId, $matches[0]),
                            'EXT' => $this->remapExt($dumpId, $matches[0]),
                            default => $matches[0],
                        };
                    },
                    $op['insert']
                );
            }
        }

        return json_encode($delta);
    }

    /**
     * Remaps a field_id for template_field records.
     * Returns the local field ID or null if not found.
     */
    public function remapFieldId(int $dumpFieldId): ?int
    {
        // Check project field mapping
        if (isset($this->projectFieldMap[$dumpFieldId])) {
            return $this->projectFieldMap[$dumpFieldId];
        }

        // Check global field mapping
        if (isset($this->globalFieldMap[$dumpFieldId])) {
            return $this->globalFieldMap[$dumpFieldId];
        }

        // Treat as external field (EXT) — try to find locally
        return $this->resolveExtFieldId($dumpFieldId);
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function clearWarnings(): void
    {
        $this->warnings = [];
    }

    private function remapPrj(int $dumpId, string $original): string
    {
        if (isset($this->projectFieldMap[$dumpId])) {
            return "PRJ:{{{$this->projectFieldMap[$dumpId]}}}";
        }

        $this->warnings[] = "PRJ placeholder veld-ID {$dumpId} niet gevonden in mapping";
        return $original;
    }

    private function remapGen(int $dumpId, string $original): string
    {
        if (isset($this->globalFieldMap[$dumpId])) {
            return "GEN:{{{$this->globalFieldMap[$dumpId]}}}";
        }

        // Try to find locally by name
        $fieldName = $this->getFieldNameFromTemp($dumpId);
        if ($fieldName === null) {
            $this->warnings[] = "GEN placeholder veld-ID {$dumpId} niet gevonden in dump";
            return $original;
        }

        $productionSchema = $this->productionSchema;
        $localId = $this->db->createCommand(
            "SELECT id FROM `{$productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = :userId",
            [':name' => $fieldName, ':userId' => $this->userId]
        )->queryScalar();

        if ($localId !== false) {
            return "GEN:{{{$localId}}}";
        }

        $this->warnings[] = "GEN placeholder \"{$fieldName}\" (dump-ID {$dumpId}) niet gevonden lokaal. "
            . "Gebruik --include-global-fields om mee te laden.";
        return $original;
    }

    private function remapExt(int $dumpId, string $original): string
    {
        $localId = $this->resolveExtFieldId($dumpId);
        if ($localId !== null) {
            return "EXT:{{{$localId}}}";
        }

        return $original;
    }

    /**
     * Resolves a global field ID from dump to a local field ID by name matching.
     */
    private function resolveGlobalFieldId(int $dumpFieldId): ?int
    {
        $fieldName = $this->getFieldNameFromTemp($dumpFieldId);
        if ($fieldName === null) {
            return null;
        }

        $productionSchema = $this->productionSchema;
        $localId = $this->db->createCommand(
            "SELECT id FROM `{$productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = :userId",
            [':name' => $fieldName, ':userId' => $this->userId]
        )->queryScalar();

        return $localId !== false ? (int) $localId : null;
    }

    /**
     * Resolves an external field ID from dump to a local field ID.
     *
     * Strategy: dump field → field name + project → local project (via label or name) → local field
     */
    private function resolveExtFieldId(int $dumpFieldId): ?int
    {
        // Get field info from temp schema
        $field = $this->db->createCommand(
            "SELECT name, project_id FROM `{$this->tempSchema}`.`field` WHERE id = :id",
            [':id' => $dumpFieldId]
        )->queryOne();

        if (!$field) {
            $this->warnings[] = "EXT veld-ID {$dumpFieldId} niet gevonden in dump";
            return null;
        }

        // Global field — resolve by name
        if ($field['project_id'] === null) {
            return $this->resolveGlobalFieldId($dumpFieldId);
        }

        // Get source project info
        $sourceProject = $this->db->createCommand(
            "SELECT label, name FROM `{$this->tempSchema}`.`project` WHERE id = :id",
            [':id' => $field['project_id']]
        )->queryOne();

        if (!$sourceProject) {
            $this->warnings[] = "EXT veld-ID {$dumpFieldId}: bronproject {$field['project_id']} niet gevonden in dump";
            return null;
        }

        // Find local project
        $productionSchema = $this->productionSchema;
        $localProjectId = null;

        // Try label match first (unique constraint guarantees max 1)
        if ($sourceProject['label'] !== null && $sourceProject['label'] !== '') {
            $localProjectId = $this->db->createCommand(
                "SELECT id FROM `{$productionSchema}`.`project`
                 WHERE label = :label AND user_id = :userId AND deleted_at IS NULL",
                [':label' => $sourceProject['label'], ':userId' => $this->userId]
            )->queryScalar();
        }

        // Fallback: name match
        if ($localProjectId === false || $localProjectId === null) {
            $matches = $this->db->createCommand(
                "SELECT id FROM `{$productionSchema}`.`project`
                 WHERE name = :name AND user_id = :userId AND deleted_at IS NULL",
                [':name' => $sourceProject['name'], ':userId' => $this->userId]
            )->queryAll();

            if (count($matches) === 1) {
                $localProjectId = $matches[0]['id'];
            } elseif (count($matches) > 1) {
                $this->warnings[] = "EXT veld \"{$field['name']}\" uit project \"{$sourceProject['name']}\": "
                    . "meerdere lokale projecten met die naam gevonden";
                return null;
            } else {
                $this->warnings[] = "EXT veld \"{$field['name']}\" uit project \"{$sourceProject['name']}\": "
                    . "bronproject niet gevonden lokaal";
                return null;
            }
        }

        // Find local field by name + project_id
        $localFieldId = $this->db->createCommand(
            "SELECT id FROM `{$productionSchema}`.`field`
             WHERE name = :name AND project_id = :projectId",
            [':name' => $field['name'], ':projectId' => $localProjectId]
        )->queryScalar();

        if ($localFieldId !== false) {
            return (int) $localFieldId;
        }

        $this->warnings[] = "EXT veld \"{$field['name']}\" uit project \"{$sourceProject['name']}\" "
            . "niet gevonden in lokaal project {$localProjectId}";
        return null;
    }

    private function getFieldNameFromTemp(int $fieldId): ?string
    {
        $name = $this->db->createCommand(
            "SELECT name FROM `{$this->tempSchema}`.`field` WHERE id = :id",
            [':id' => $fieldId]
        )->queryScalar();

        return $name !== false ? $name : null;
    }

}
