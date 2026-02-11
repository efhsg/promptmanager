<?php

namespace app\services\projectload;

use yii\db\Connection;

/**
 * Inspects database schemas via INFORMATION_SCHEMA for dynamic column detection.
 *
 * Determines column lists at runtime rather than hardcoding them,
 * preventing the "forgotten column after migration" maintenance burden.
 */
class SchemaInspector
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Returns column metadata for a table in the given schema.
     *
     * @return array<string, array{nullable: bool, default: mixed, dataType: string, extra: string}>
     */
    public function getColumnInfo(string $schema, string $table): array
    {
        $rows = $this->db->createCommand(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION',
            [':schema' => $schema, ':table' => $table]
        )->queryAll();

        $columns = [];
        foreach ($rows as $row) {
            $columns[$row['COLUMN_NAME']] = [
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'default' => $row['COLUMN_DEFAULT'],
                'dataType' => $row['DATA_TYPE'],
                'extra' => $row['EXTRA'],
            ];
        }

        return $columns;
    }

    /**
     * Returns column names for a table, excluding auto-increment PK and configured excludes.
     *
     * @param string[] $excludeColumns
     * @return string[]
     */
    public function getInsertColumns(string $schema, string $table, array $excludeColumns = [], bool $filterAutoIncrement = true): array
    {
        $columnInfo = $this->getColumnInfo($schema, $table);
        $columns = [];

        foreach ($columnInfo as $name => $info) {
            if ($filterAutoIncrement && str_contains($info['extra'], 'auto_increment')) {
                continue;
            }
            if (in_array($name, $excludeColumns, true)) {
                continue;
            }
            $columns[] = $name;
        }

        return $columns;
    }

    /**
     * Checks which tables exist in a schema.
     *
     * @param string[] $tables
     * @return array<string, bool>
     */
    public function getExistingTables(string $schema, array $tables): array
    {
        $rows = $this->db->createCommand(
            'SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :schema',
            [':schema' => $schema]
        )->queryColumn();

        $existing = array_flip($rows);
        $result = [];
        foreach ($tables as $table) {
            $result[$table] = isset($existing[$table]);
        }

        return $result;
    }

    /**
     * Checks if a specific table exists in a schema.
     */
    public function tableExists(string $schema, string $table): bool
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            [':schema' => $schema, ':table' => $table]
        )->queryScalar();

        return (int)$count > 0;
    }

    /**
     * Returns column names that exist in a specific table and schema.
     *
     * @return string[]
     */
    public function getColumnNames(string $schema, string $table): array
    {
        return $this->db->createCommand(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION',
            [':schema' => $schema, ':table' => $table]
        )->queryColumn();
    }

    /**
     * Gets the production schema name from the current DB connection.
     */
    public function getProductionSchema(): string
    {
        return $this->db->createCommand('SELECT DATABASE()')->queryScalar();
    }

    /**
     * Returns a PHP fallback value for a column not present in the source dump.
     */
    public static function getPhpFallbackValue(array $columnInfo): mixed
    {
        if (empty($columnInfo)) {
            return null;
        }
        if ($columnInfo['nullable']) {
            return null;
        }
        if ($columnInfo['default'] !== null) {
            return $columnInfo['default'];
        }

        $dataType = strtolower($columnInfo['dataType']);
        if (in_array($dataType, ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext'], true)) {
            return '';
        }
        if (in_array($dataType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'], true)) {
            return 0;
        }
        if (in_array($dataType, ['datetime', 'timestamp'], true)) {
            return date('Y-m-d H:i:s');
        }
        if ($dataType === 'date') {
            return date('Y-m-d');
        }
        if ($dataType === 'json') {
            return '{}';
        }

        return '';
    }

    /**
     * Returns all schema names matching a pattern.
     *
     * @return string[]
     */
    public function getSchemasByPattern(string $pattern): array
    {
        return $this->db->createCommand(
            'SELECT SCHEMA_NAME
             FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME LIKE :pattern',
            [':pattern' => $pattern]
        )->queryColumn();
    }

    /**
     * Gets the creation time of a schema by checking the oldest table's CREATE_TIME.
     */
    public function getSchemaAge(string $schema): ?string
    {
        return $this->db->createCommand(
            'SELECT MIN(CREATE_TIME)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :schema',
            [':schema' => $schema]
        )->queryScalar() ?: null;
    }
}
