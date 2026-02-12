<?php

namespace app\services\projectload;

/**
 * DTO for project load operation results.
 *
 * Tracks per-project loading statistics, ID mappings, warnings, and errors.
 */
class LoadReport
{
    /** @var array<int, array{name: string, status: string, inserted: array, deleted: array, warnings: string[], error: ?string}> */
    private array $projects = [];

    /** @var array<string, array<int, int>> entity => [sourceId => destId] */
    private array $idMappings = [];

    /** @var string[] */
    private array $globalWarnings = [];

    public function initProject(int $dumpProjectId, string $name): void
    {
        $this->projects[$dumpProjectId] = [
            'name' => $name,
            'status' => 'pending',
            'localProjectId' => null,
            'isReplacement' => false,
            'inserted' => [],
            'deleted' => [],
            'warnings' => [],
            'error' => null,
        ];
    }

    public function setProjectLocalMatch(int $dumpProjectId, ?int $localProjectId, bool $isReplacement): void
    {
        $this->projects[$dumpProjectId]['localProjectId'] = $localProjectId;
        $this->projects[$dumpProjectId]['isReplacement'] = $isReplacement;
    }

    public function setProjectStatus(int $dumpProjectId, string $status): void
    {
        $this->projects[$dumpProjectId]['status'] = $status;
    }

    public function setProjectError(int $dumpProjectId, string $error): void
    {
        $this->projects[$dumpProjectId]['status'] = 'error';
        $this->projects[$dumpProjectId]['error'] = $error;
    }

    public function addInserted(int $dumpProjectId, string $entity, int $count = 1): void
    {
        $this->projects[$dumpProjectId]['inserted'][$entity]
            = ($this->projects[$dumpProjectId]['inserted'][$entity] ?? 0) + $count;
    }

    public function addDeleted(int $dumpProjectId, string $entity, int $count = 1): void
    {
        $this->projects[$dumpProjectId]['deleted'][$entity]
            = ($this->projects[$dumpProjectId]['deleted'][$entity] ?? 0) + $count;
    }

    public function addWarning(int $dumpProjectId, string $message): void
    {
        $this->projects[$dumpProjectId]['warnings'][] = $message;
    }

    public function addGlobalWarning(string $message): void
    {
        $this->globalWarnings[] = $message;
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

    /**
     * @return array<int, array>
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    /**
     * @return string[]
     */
    public function getGlobalWarnings(): array
    {
        return $this->globalWarnings;
    }

    public function hasErrors(): bool
    {
        foreach ($this->projects as $project) {
            if ($project['status'] === 'error') {
                return true;
            }
        }
        return false;
    }

    public function getSuccessCount(): int
    {
        $count = 0;
        foreach ($this->projects as $project) {
            if ($project['status'] === 'success') {
                $count++;
            }
        }
        return $count;
    }

    public function getErrorCount(): int
    {
        $count = 0;
        foreach ($this->projects as $project) {
            if ($project['status'] === 'error') {
                $count++;
            }
        }
        return $count;
    }

    public function getSkippedCount(): int
    {
        $count = 0;
        foreach ($this->projects as $project) {
            if ($project['status'] === 'skipped') {
                $count++;
            }
        }
        return $count;
    }

    public function getReplacementCount(): int
    {
        $count = 0;
        foreach ($this->projects as $project) {
            if ($project['isReplacement'] && $project['status'] === 'success') {
                $count++;
            }
        }
        return $count;
    }

    public function getNewCount(): int
    {
        $count = 0;
        foreach ($this->projects as $project) {
            if (!$project['isReplacement'] && $project['status'] === 'success') {
                $count++;
            }
        }
        return $count;
    }
}
