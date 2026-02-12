<?php

namespace tests\unit\services\projectload;

use app\services\projectload\EntityConfig;
use Codeception\Test\Unit;
use Yii;

/**
 * Integrity tests for EntityConfig — verifies that configuration
 * matches the current database schema.
 */
class EntityConfigIntegrityTest extends Unit
{
    private string $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = Yii::$app->db->createCommand('SELECT DATABASE()')->queryScalar();
    }

    public function testEntityTablesExistInSchema(): void
    {
        $entities = EntityConfig::getEntities();

        foreach ($entities as $entityName => $config) {
            $table = $config['table'];
            $exists = (int) Yii::$app->db->createCommand(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
                [':schema' => $this->schema, ':table' => $table]
            )->queryScalar();

            $this->assertEquals(
                1,
                $exists,
                "Tabel '{$table}' voor entiteit '{$entityName}' bestaat niet in schema '{$this->schema}'"
            );
        }
    }

    public function testExcludedColumnsExistInSchema(): void
    {
        foreach (EntityConfig::EXCLUDED_COLUMNS as $entity => $columns) {
            $table = EntityConfig::getEntities()[$entity]['table'];

            $schemaColumns = Yii::$app->db->createCommand(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
                [':schema' => $this->schema, ':table' => $table]
            )->queryColumn();

            foreach ($columns as $column) {
                $this->assertContains(
                    $column,
                    $schemaColumns,
                    "Excluded kolom '{$column}' bestaat niet in tabel '{$table}'"
                );
            }
        }
    }

    public function testOverrideColumnsExistInSchema(): void
    {
        foreach (EntityConfig::COLUMN_OVERRIDES as $entity => $columns) {
            $table = EntityConfig::getEntities()[$entity]['table'];

            $schemaColumns = Yii::$app->db->createCommand(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
                [':schema' => $this->schema, ':table' => $table]
            )->queryColumn();

            foreach ($columns as $column) {
                $this->assertContains(
                    $column,
                    $schemaColumns,
                    "Override kolom '{$column}' bestaat niet in tabel '{$table}'"
                );
            }
        }
    }

    public function testForeignKeyColumnsExistInSchema(): void
    {
        $entities = EntityConfig::getEntities();

        foreach ($entities as $entityName => $config) {
            $table = $config['table'];

            $schemaColumns = Yii::$app->db->createCommand(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
                [':schema' => $this->schema, ':table' => $table]
            )->queryColumn();

            foreach ($config['foreignKeys'] as $fkColumn => $targetEntity) {
                $this->assertContains(
                    $fkColumn,
                    $schemaColumns,
                    "FK kolom '{$fkColumn}' van entiteit '{$entityName}' bestaat niet in tabel '{$table}'"
                );

                // Verify target entity exists
                $this->assertArrayHasKey(
                    $targetEntity,
                    $entities,
                    "FK target entiteit '{$targetEntity}' van '{$entityName}.{$fkColumn}' bestaat niet in EntityConfig"
                );
            }
        }
    }

    public function testInsertOrderContainsAllEntities(): void
    {
        $entities = array_keys(EntityConfig::getEntities());
        $insertOrder = EntityConfig::getInsertOrder();

        sort($entities);
        $sortedOrder = $insertOrder;
        sort($sortedOrder);

        $this->assertEquals(
            $entities,
            $sortedOrder,
            'Insert-volgorde bevat niet alle entiteiten'
        );
    }

    public function testInsertOrderRespectsDependencies(): void
    {
        $entities = EntityConfig::getEntities();
        $insertOrder = EntityConfig::getInsertOrder();
        $positions = array_flip($insertOrder);

        foreach ($entities as $entityName => $config) {
            foreach ($config['foreignKeys'] as $fkColumn => $targetEntity) {
                if ($targetEntity === $entityName) {
                    continue; // Self-reference
                }

                $this->assertArrayHasKey($targetEntity, $positions, "FK target '{$targetEntity}' niet in insert-volgorde");
                $this->assertArrayHasKey($entityName, $positions, "Entiteit '{$entityName}' niet in insert-volgorde");

                // Special case: project_linked_project's linked_project_id references project,
                // but the linked project might not be in this load run — skip this check
                if ($entityName === 'project_linked_project' && $fkColumn === 'linked_project_id') {
                    continue;
                }

                $this->assertLessThan(
                    $positions[$entityName],
                    $positions[$targetEntity],
                    "Entiteit '{$targetEntity}' moet vóór '{$entityName}' komen in insert-volgorde (FK: {$fkColumn})"
                );
            }
        }
    }

    public function testAutoIncrementEntitiesAreValid(): void
    {
        $entities = array_keys(EntityConfig::getEntities());
        $autoIncrement = EntityConfig::getAutoIncrementEntities();

        foreach ($autoIncrement as $entity) {
            $this->assertContains(
                $entity,
                $entities,
                "Auto-increment entiteit '{$entity}' bestaat niet in EntityConfig"
            );
        }

        // Verify template_field and project_linked_project are NOT in auto-increment list
        $this->assertNotContains('template_field', $autoIncrement);
        $this->assertNotContains('project_linked_project', $autoIncrement);
    }
}
