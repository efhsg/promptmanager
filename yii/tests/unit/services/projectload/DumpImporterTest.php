<?php

namespace tests\unit\services\projectload;

use app\services\projectload\DumpImporter;
use app\services\projectload\SchemaInspector;
use Codeception\Test\Unit;
use RuntimeException;
use Yii;

class DumpImporterTest extends Unit
{
    private DumpImporter $importer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $db = Yii::$app->db;
        $inspector = new SchemaInspector($db);
        $this->importer = new DumpImporter($db, $inspector);
        $this->tempDir = sys_get_temp_dir() . '/projectload_test_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function _after(): void
    {
        // Cleanup temp files
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
        parent::_after();
    }

    public function testValidateDumpFileThrowsWhenFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('niet gevonden');
        $this->importer->validateDumpFile('/nonexistent/file.sql');
    }

    public function testValidateDumpFileThrowsWhenWrongExtension(): void
    {
        $file = $this->tempDir . '/dump.txt';
        file_put_contents($file, 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ongeldig bestandstype');
        $this->importer->validateDumpFile($file);
    }

    public function testValidateDumpFileAcceptsSqlExtension(): void
    {
        $file = $this->tempDir . '/dump.sql';
        file_put_contents($file, 'SELECT 1;');

        $result = $this->importer->validateDumpFile($file);
        $this->assertEquals(realpath($file), $result);
    }

    public function testValidateDumpFileAcceptsDumpExtension(): void
    {
        $file = $this->tempDir . '/dump.dump';
        file_put_contents($file, 'SELECT 1;');

        $result = $this->importer->validateDumpFile($file);
        $this->assertEquals(realpath($file), $result);
    }

    public function testCreateAndDropTempSchema(): void
    {
        $schema = $this->importer->createTempSchema();

        try {
            $this->assertStringStartsWith('yii_load_temp_', $schema);

            // Verify schema exists
            $exists = (int)Yii::$app->db->createCommand(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name',
                [':name' => $schema]
            )->queryScalar();
            $this->assertEquals(1, $exists);
        } finally {
            $this->importer->dropSchema($schema);
        }

        // Verify schema removed
        $exists = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name',
            [':name' => $schema]
        )->queryScalar();
        $this->assertEquals(0, $exists);
    }

    public function testImportDumpWithSimpleSql(): void
    {
        $this->skipIfMysqlCliMissing();
        $schema = $this->importer->createTempSchema();

        try {
            $file = $this->tempDir . '/test.sql';
            file_put_contents($file, "CREATE TABLE test_table (id INT PRIMARY KEY, name VARCHAR(100));\n"
                . "INSERT INTO test_table VALUES (1, 'hello');\n");

            $this->importer->importDump($file, $schema);

            $result = Yii::$app->db->createCommand(
                "SELECT name FROM `{$schema}`.`test_table` WHERE id = 1"
            )->queryScalar();

            $this->assertEquals('hello', $result);
        } finally {
            $this->importer->dropSchema($schema);
        }
    }

    public function testImportDumpStripsUseStatements(): void
    {
        $this->skipIfMysqlCliMissing();
        $schema = $this->importer->createTempSchema();

        try {
            $file = $this->tempDir . '/test.sql';
            file_put_contents($file, "USE some_other_db;\n"
                . "CREATE TABLE test_table2 (id INT PRIMARY KEY);\n"
                . "INSERT INTO test_table2 VALUES (1);\n");

            $this->importer->importDump($file, $schema);

            $result = Yii::$app->db->createCommand(
                "SELECT COUNT(*) FROM `{$schema}`.`test_table2`"
            )->queryScalar();

            $this->assertEquals(1, (int)$result);
        } finally {
            $this->importer->dropSchema($schema);
        }
    }

    public function testCleanupRemovesOrphanedSchemas(): void
    {
        // Create a schema and a table with old timestamp
        $schemaName = 'yii_load_temp_cleanup_test_' . getmypid();
        Yii::$app->db->createCommand(
            "CREATE DATABASE IF NOT EXISTS `{$schemaName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        // An empty schema should be cleaned up (no tables = always orphaned)
        $cleaned = $this->importer->cleanupOrphanedSchemas();

        $this->assertArrayHasKey($schemaName, $cleaned);

        // Verify it's gone
        $exists = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name',
            [':name' => $schemaName]
        )->queryScalar();
        $this->assertEquals(0, $exists);
    }

    public function testGetTempSchemaPrefix(): void
    {
        $this->assertEquals('yii_load_temp_', $this->importer->getTempSchemaPrefix());
    }

    private function skipIfMysqlCliMissing(): void
    {
        exec('which mysql 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('mysql CLI binary not available');
        }
    }
}
