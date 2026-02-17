<?php

namespace app\services\projectload;

use PDO;
use PDOException;
use common\enums\LogCategory;
use RuntimeException;
use Yii;
use yii\db\Connection;

/**
 * Handles dump file validation, temp schema creation, and dump import.
 */
class DumpImporter
{
    private const TEMP_SCHEMA_PREFIX = 'yii_load_temp_';
    private const ORPHAN_THRESHOLD_SECONDS = 3600; // 1 hour
    private const ALLOWED_EXTENSIONS = ['sql', 'dump'];

    private Connection $db;
    private SchemaInspector $schemaInspector;

    public function __construct(Connection $db, SchemaInspector $schemaInspector)
    {
        $this->db = $db;
        $this->schemaInspector = $schemaInspector;
    }

    /**
     * Validates the dump file path.
     *
     * @throws RuntimeException
     */
    public function validateDumpFile(string $filePath): string
    {
        $realPath = realpath($filePath);
        if ($realPath === false || !is_file($realPath)) {
            throw new RuntimeException("Dump-bestand niet gevonden: {$filePath}");
        }
        if (!is_readable($realPath)) {
            throw new RuntimeException("Dump-bestand niet leesbaar: {$realPath}");
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException(
                "Ongeldig bestandstype '.{$extension}'. Verwacht: .sql of .dump"
            );
        }

        $fileSize = filesize($realPath);
        if ($fileSize > 100 * 1024 * 1024) {
            Yii::warning("Groot dump-bestand: " . round($fileSize / 1024 / 1024) . "MB", LogCategory::APPLICATION->value);
        }

        return $realPath;
    }

    /**
     * Creates a temporary schema for dump import.
     *
     * @throws RuntimeException
     */
    public function createTempSchema(): string
    {
        $schemaName = self::TEMP_SCHEMA_PREFIX . getmypid();

        // Drop if exists (leftover from same PID — unlikely but safe)
        if ($this->schemaInspector->tableExists('INFORMATION_SCHEMA', 'SCHEMATA')) {
            $existing = $this->schemaInspector->getSchemasByPattern($schemaName);
            if (!empty($existing)) {
                $this->dropSchema($schemaName);
            }
        }

        $this->db->createCommand(
            "CREATE DATABASE `{$schemaName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        return $schemaName;
    }

    /**
     * Imports a dump file into the temporary schema via PDO.
     *
     * @throws RuntimeException
     */
    public function importDump(string $filePath, string $tempSchema): void
    {
        $filteredContent = $this->filterDumpContent($filePath);

        $dsn = $this->parseDsn();
        $pdoDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dsn['host'], $dsn['port'], $tempSchema);

        try {
            $pdo = new PDO(
                $pdoDsn,
                $this->db->username,
                $this->db->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_LOCAL_INFILE => false,
                ]
            );
            $pdo->exec($filteredContent);
        } catch (PDOException $e) {
            throw new RuntimeException("MySQL import mislukt: " . $e->getMessage());
        }
    }

    /**
     * Drops a schema.
     */
    public function dropSchema(string $schemaName): void
    {
        $this->db->createCommand("DROP DATABASE IF EXISTS `{$schemaName}`")->execute();
    }

    /**
     * Cleans up orphaned temp schemas older than the threshold.
     *
     * @return array<string, ?string> schemaName => age description
     */
    public function cleanupOrphanedSchemas(): array
    {
        $schemas = $this->schemaInspector->getSchemasByPattern(self::TEMP_SCHEMA_PREFIX . '%');
        $cleaned = [];
        $now = time();

        foreach ($schemas as $schema) {
            $createTime = $this->schemaInspector->getSchemaAge($schema);

            if ($createTime === null) {
                // Empty schema (no tables) — always orphaned
                $this->dropSchema($schema);
                $cleaned[$schema] = 'leeg schema';
                continue;
            }

            $age = $now - strtotime($createTime);
            if ($age > self::ORPHAN_THRESHOLD_SECONDS) {
                $this->dropSchema($schema);
                $cleaned[$schema] = $this->formatAge($age);
            }
        }

        return $cleaned;
    }

    /**
     * Returns temp schema prefix for external use.
     */
    public function getTempSchemaPrefix(): string
    {
        return self::TEMP_SCHEMA_PREFIX;
    }

    /**
     * Filters dangerous statements from dump content.
     */
    private function filterDumpContent(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Kan dump-bestand niet lezen: {$filePath}");
        }

        // Strip USE, CREATE DATABASE, and DROP DATABASE statements
        $filtered = preg_replace(
            '/^\s*(USE\s+|CREATE\s+DATABASE\s+|DROP\s+DATABASE\s+).*$/mi',
            '',
            $content
        );

        return $filtered;
    }

    /**
     * Parses the DSN from the DB connection.
     *
     * @return array{host: string, port: string}
     */
    private function parseDsn(): array
    {
        $dsn = $this->db->dsn;
        $host = '127.0.0.1';
        $port = '3306';

        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            $host = $matches[1];
        }
        if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
            $port = $matches[1];
        }

        return ['host' => $host, 'port' => $port];
    }

    /**
     * Formats seconds into a human-readable age string.
     */
    private function formatAge(int $seconds): string
    {
        if ($seconds >= 86400) {
            $days = floor($seconds / 86400);
            return $days . ' ' . ($days === 1.0 ? 'dag' : 'dagen') . ' oud';
        }
        $hours = floor($seconds / 3600);
        return $hours . ' ' . ($hours === 1.0 ? 'uur' : 'uur') . ' oud';
    }
}
