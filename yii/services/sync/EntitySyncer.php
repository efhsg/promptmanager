<?php

namespace app\services\sync;

use Exception;
use yii\db\Connection;

/**
 * Handles per-entity sync operations with ID-independent natural key matching.
 */
class EntitySyncer
{
    private ConflictResolver $conflictResolver;
    private RecordFetcher $recordFetcher;
    private array $entityDefinitions;
    private array $sourceKeyLookup = [];
    private array $destKeyLookup = [];
    private array $destKeyToId = [];

    public function __construct()
    {
        $this->conflictResolver = new ConflictResolver();
        $this->recordFetcher = new RecordFetcher();
        $this->entityDefinitions = EntityDefinitions::getAll();
    }

    public function syncEntity(
        string $entity,
        Connection $sourceDb,
        Connection $destDb,
        SyncReport $report,
        int $userId,
        bool $dryRun = false
    ): void {
        $definition = $this->entityDefinitions[$entity] ?? null;
        if ($definition === null) {
            $report->addError($entity, "Unknown entity type: {$entity}");
            return;
        }

        $sourceRecords = $this->recordFetcher->fetch($sourceDb, $definition, $userId, $entity);
        $destRecords = $this->recordFetcher->fetch($destDb, $definition, $userId, $entity);

        $this->buildKeyLookup($entity, $sourceRecords, 'source', $report);
        $this->buildKeyLookup($entity, $destRecords, 'dest', $report);

        $destIndex = $this->indexDestinationRecords($entity, $destRecords);

        foreach ($sourceRecords as $sourceRecord) {
            $this->syncRecord($entity, $sourceRecord, $destIndex, $definition, $destDb, $report, $dryRun);
        }
    }

    public function getSyncOrder(): array
    {
        return EntityDefinitions::getSyncOrder();
    }

    public function getEntityDefinitions(): array
    {
        return $this->entityDefinitions;
    }

    private function indexDestinationRecords(string $entity, array $destRecords): array
    {
        $destIndex = [];
        foreach ($destRecords as $record) {
            $key = $this->destKeyLookup[$entity][$record['id']] ?? null;
            if ($key !== null) {
                $destIndex[$key] = $record;
                $this->destKeyToId[$entity][$key] = $record['id'];
            }
        }
        return $destIndex;
    }

    private function syncRecord(
        string $entity,
        array $sourceRecord,
        array $destIndex,
        array $definition,
        Connection $destDb,
        SyncReport $report,
        bool $dryRun
    ): void {
        $semanticKey = $this->sourceKeyLookup[$entity][$sourceRecord['id']] ?? null;
        if ($semanticKey === null) {
            return;
        }

        $mappedRecord = $this->mapForeignKeys($sourceRecord, $definition['foreignKeys'], $report, $entity);
        if ($mappedRecord === null) {
            return;
        }

        $destRecord = $destIndex[$semanticKey] ?? null;

        if ($destRecord === null) {
            $this->insertRecord($destDb, $definition, $mappedRecord, $report, $entity, $sourceRecord['id'], $dryRun);
            return;
        }

        $sourceUpdated = $sourceRecord['updated_at'] ?? $sourceRecord['created_at'] ?? null;
        $destUpdated = $destRecord['updated_at'] ?? $destRecord['created_at'] ?? null;

        if ($this->conflictResolver->isSourceNewer($sourceUpdated, $destUpdated)) {
            $this->updateRecord($destDb, $definition, $mappedRecord, $destRecord['id'], $report, $entity, $dryRun);
        } else {
            $report->addSkipped($entity);
        }

        $report->addIdMapping($entity, $sourceRecord['id'], $destRecord['id']);
    }

    private function buildKeyLookup(string $entity, array $records, string $side, SyncReport $report): void
    {
        $lookup = $side === 'source' ? $this->sourceKeyLookup : $this->destKeyLookup;

        if (!isset($lookup[$entity])) {
            $lookup[$entity] = [];
        }

        foreach ($records as $record) {
            $key = $this->buildSemanticKey($entity, $record, $side, $report);
            if ($key !== null) {
                $lookup[$entity][$record['id']] = $key;
            }
        }

        if ($side === 'source') {
            $this->sourceKeyLookup = $lookup;
        } else {
            $this->destKeyLookup = $lookup;
        }
    }

    private function buildSemanticKey(string $entity, array $record, string $side, SyncReport $report): ?string
    {
        $definition = $this->entityDefinitions[$entity];
        $parts = [];

        foreach ($definition['naturalKeys'] as $keyField) {
            $parts[] = $record[$keyField] ?? null;
        }

        foreach ($definition['foreignKeys'] as $fkColumn => $parentEntity) {
            $fkValue = $record[$fkColumn] ?? null;
            if ($fkValue === null) {
                $parts[] = null;
                continue;
            }

            $lookup = $side === 'source' ? $this->sourceKeyLookup : $this->destKeyLookup;
            $parentKey = $lookup[$parentEntity][$fkValue] ?? null;

            if ($parentKey === null) {
                $report->addError($entity, "Cannot resolve FK {$fkColumn}={$fkValue} for {$parentEntity}");
                return null;
            }

            $parts[] = $parentKey;
        }

        return json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function mapForeignKeys(array $record, array $foreignKeys, SyncReport $report, string $entity): ?array
    {
        foreach ($foreignKeys as $column => $targetEntity) {
            $sourceId = $record[$column] ?? null;
            if ($sourceId === null) {
                continue;
            }

            $mappedId = $report->getMappedId($targetEntity, (int) $sourceId);
            if ($mappedId === null) {
                $report->addError($entity, "FK target not found: {$column}={$sourceId} -> {$targetEntity}");
                return null;
            }

            $record[$column] = $mappedId;
        }

        return $record;
    }

    private function insertRecord(
        Connection $db,
        array $definition,
        array $record,
        SyncReport $report,
        string $entity,
        int $sourceId,
        bool $dryRun
    ): void {
        $data = [];
        foreach ($definition['columns'] as $column) {
            if (array_key_exists($column, $record)) {
                $data[$column] = $record[$column];
            }
        }

        if ($dryRun) {
            $report->addInserted($entity);
            $report->addIdMapping($entity, $sourceId, $sourceId);
            return;
        }

        try {
            $db->createCommand()->insert($definition['table'], $data)->execute();
            $newId = (int) $db->getLastInsertID();
            $report->addInserted($entity);
            $report->addIdMapping($entity, $sourceId, $newId);
        } catch (Exception $e) {
            $report->addError($entity, "Insert failed: " . $e->getMessage());
        }
    }

    private function updateRecord(
        Connection $db,
        array $definition,
        array $record,
        int $destId,
        SyncReport $report,
        string $entity,
        bool $dryRun
    ): void {
        $data = [];
        foreach ($definition['columns'] as $column) {
            if (array_key_exists($column, $record) && $column !== 'created_at') {
                $data[$column] = $record[$column];
            }
        }

        if ($dryRun) {
            $report->addUpdated($entity);
            return;
        }

        try {
            $db->createCommand()->update($definition['table'], $data, ['id' => $destId])->execute();
            $report->addUpdated($entity);
        } catch (Exception $e) {
            $report->addError($entity, "Update failed for ID {$destId}: " . $e->getMessage());
        }
    }
}
