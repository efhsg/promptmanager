<?php

namespace app\services\sync;

/**
 * DTO for sync operation results.
 */
class SyncReport
{
    public array $inserted = [];
    public array $updated = [];
    public array $skipped = [];
    public array $errors = [];
    public array $idMappings = [];

    public function addInserted(string $entity): void
    {
        $this->inserted[$entity] = ($this->inserted[$entity] ?? 0) + 1;
    }

    public function addUpdated(string $entity): void
    {
        $this->updated[$entity] = ($this->updated[$entity] ?? 0) + 1;
    }

    public function addSkipped(string $entity): void
    {
        $this->skipped[$entity] = ($this->skipped[$entity] ?? 0) + 1;
    }

    public function addError(string $entity, string $message): void
    {
        if (!isset($this->errors[$entity])) {
            $this->errors[$entity] = [];
        }
        $this->errors[$entity][] = $message;
    }

    public function addIdMapping(string $entity, int $sourceId, int $destId): void
    {
        if (!isset($this->idMappings[$entity])) {
            $this->idMappings[$entity] = [];
        }
        $this->idMappings[$entity][$sourceId] = $destId;
    }

    public function getMappedId(string $entity, int $sourceId): ?int
    {
        return $this->idMappings[$entity][$sourceId] ?? null;
    }

    public function hasErrors(): bool
    {
        foreach ($this->errors as $entityErrors) {
            if (count($entityErrors) > 0) {
                return true;
            }
        }
        return false;
    }

    public function getTotalInserted(): int
    {
        return array_sum($this->inserted);
    }

    public function getTotalUpdated(): int
    {
        return array_sum($this->updated);
    }

    public function getTotalSkipped(): int
    {
        return array_sum($this->skipped);
    }

    public function getTotalErrors(): int
    {
        $count = 0;
        foreach ($this->errors as $entityErrors) {
            $count += count($entityErrors);
        }
        return $count;
    }

    public function toArray(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'totals' => [
                'inserted' => $this->getTotalInserted(),
                'updated' => $this->getTotalUpdated(),
                'skipped' => $this->getTotalSkipped(),
                'errors' => $this->getTotalErrors(),
            ],
        ];
    }
}
