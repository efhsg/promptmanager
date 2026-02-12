<?php

namespace tests\unit\services\projectload;

use app\services\projectload\EntityLoader;
use app\services\projectload\SchemaInspector;
use Codeception\Test\Unit;
use Yii;
use yii\db\Connection;

/**
 * Tests for EntityLoader — entity fetching, deletion, and insertion.
 *
 * Does NOT use _fixtures() because DDL statements (CREATE DATABASE)
 * implicitly commit the transaction, breaking Codeception's transaction-based cleanup.
 * Instead, ensures required user exists manually and cleans up in _after().
 */
class EntityLoaderTest extends Unit
{
    private Connection $db;
    private SchemaInspector $inspector;
    private string $productionSchema;
    private string $tempSchema;

    /** @var int[] project IDs to clean up */
    private array $createdProjectIds = [];

    public function _before(): void
    {
        $this->db = Yii::$app->db;
        $this->inspector = new SchemaInspector($this->db);
        $this->productionSchema = $this->inspector->getProductionSchema();
        $this->tempSchema = 'yii_load_temp_eltest_' . getmypid();
        $this->createdProjectIds = [];

        // Ensure user_id=1 exists (needed for insertProject tests)
        $this->ensureUserExists(1, 'testuser_eltest');

        $this->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
        $this->db->createCommand(
            "CREATE DATABASE `{$this->tempSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        $tables = ['project', 'context', 'field', 'field_option', 'prompt_template',
            'template_field', 'prompt_instance', 'note', 'project_linked_project'];

        foreach ($tables as $table) {
            $this->db->createCommand(
                "CREATE TABLE IF NOT EXISTS `{$this->tempSchema}`.`{$table}` LIKE `{$this->productionSchema}`.`{$table}`"
            )->execute();
        }
    }

    public function _after(): void
    {
        // Clean up created projects
        foreach (array_reverse($this->createdProjectIds) as $id) {
            $this->db->createCommand(
                "DELETE FROM `{$this->productionSchema}`.`prompt_instance`
                 WHERE template_id IN (SELECT id FROM `{$this->productionSchema}`.`prompt_template` WHERE project_id = :id)",
                [':id' => $id]
            )->execute();
            $this->db->createCommand(
                "DELETE FROM `{$this->productionSchema}`.`project` WHERE id = :id",
                [':id' => $id]
            )->execute();
        }

        if (isset($this->tempSchema)) {
            $this->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
        }
    }

    private function ensureUserExists(int $id, string $username): void
    {
        $exists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`user` WHERE id = :id",
            [':id' => $id]
        )->queryScalar();
        if ($exists === 0) {
            $this->db->createCommand()->insert("`{$this->productionSchema}`.`user`", [
                'id' => $id,
                'username' => $username,
                'auth_key' => 'test_key_' . $id,
                'password_hash' => '$2y$13$dummyhash',
                'access_token' => $id . '_token',
                'access_token_hash' => hash('sha256', $id . '_token'),
                'email' => $username . '@test.com',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
    }

    public function testFetchProjectReturnsProjectFromTempSchema(): void
    {
        $this->insertTempProject(100, 'TempProject', 1);
        $loader = $this->createLoader();

        $project = $loader->fetchProject(100);

        $this->assertNotNull($project);
        $this->assertEquals('TempProject', $project['name']);
    }

    public function testFetchProjectReturnsNullWhenNotFound(): void
    {
        $loader = $this->createLoader();

        $this->assertNull($loader->fetchProject(99999));
    }

    public function testFetchAllProjectsReturnsAllFromTemp(): void
    {
        $this->insertTempProject(100, 'Project A', 1);
        $this->insertTempProject(101, 'Project B', 1);
        $loader = $this->createLoader();

        $projects = $loader->fetchAllProjects();

        $this->assertCount(2, $projects);
    }

    public function testFetchFromTempReturnsByParentColumn(): void
    {
        $this->insertTempProject(100, 'TestProject', 1);
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`context`",
            [
                'id' => 200,
                'project_id' => 100,
                'name' => 'TestContext',
                'content' => 'content',
                'share' => 0,
                'is_default' => 0,
                'order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();

        $loader = $this->createLoader();
        $records = $loader->fetchFromTemp('context', 'project_id', 100);

        $this->assertCount(1, $records);
        $this->assertEquals('TestContext', $records[0]['name']);
    }

    public function testFetchFromTempReturnsEmptyForNoResults(): void
    {
        $loader = $this->createLoader();
        $records = $loader->fetchFromTemp('context', 'project_id', 99999);

        $this->assertEmpty($records);
    }

    public function testCountInTempCountsRecords(): void
    {
        $this->insertTempProject(100, 'TestProject', 1);
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`context`",
            [
                'id' => 200,
                'project_id' => 100,
                'name' => 'Ctx1',
                'content' => 'c',
                'share' => 0,
                'is_default' => 0,
                'order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`context`",
            [
                'id' => 201,
                'project_id' => 100,
                'name' => 'Ctx2',
                'content' => 'c',
                'share' => 0,
                'is_default' => 0,
                'order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();

        $loader = $this->createLoader();
        $this->assertEquals(2, $loader->countInTemp('context', 'project_id', 100));
        $this->assertEquals(0, $loader->countInTemp('context', 'project_id', 99999));
    }

    public function testDeleteLocalProjectRemovesProjectAndChildren(): void
    {
        // Create a test project to delete (not a fixture project)
        $this->db->createCommand()->insert(
            "`{$this->productionSchema}`.`project`",
            [
                'user_id' => 1,
                'name' => 'ToDelete_' . getmypid(),
                'description' => 'Will be deleted',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
        $projectId = (int) $this->db->getLastInsertID();

        $this->db->createCommand()->insert(
            "`{$this->productionSchema}`.`context`",
            [
                'project_id' => $projectId,
                'name' => 'DeleteCtx',
                'content' => 'c',
                'share' => 0,
                'is_default' => 0,
                'order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();

        $loader = $this->createLoader();
        $loader->deleteLocalProject($projectId);

        $exists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $this->assertEquals(0, $exists);

        $ctxExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`context` WHERE project_id = :id",
            [':id' => $projectId]
        )->queryScalar();
        $this->assertEquals(0, $ctxExists);
    }

    public function testIdMappingStoresAndRetrieves(): void
    {
        $loader = $this->createLoader();

        $loader->addIdMapping('project', 100, 200);
        $loader->addIdMapping('field', 10, 20);

        $this->assertEquals(200, $loader->getMappedId('project', 100));
        $this->assertEquals(20, $loader->getMappedId('field', 10));
        $this->assertNull($loader->getMappedId('project', 999));
    }

    public function testClearIdMappingsResetsAll(): void
    {
        $loader = $this->createLoader();

        $loader->addIdMapping('project', 100, 200);
        $loader->clearIdMappings();

        $this->assertNull($loader->getMappedId('project', 100));
        $this->assertEmpty($loader->getIdMappings());
    }

    public function testInsertProjectWithAutoIncrementId(): void
    {
        $this->insertTempProject(901, 'NewAutoProject', 99);
        $loader = $this->createLoader();

        $insertColumns = $this->inspector->getInsertColumns($this->productionSchema, 'project');
        $colInfo = $this->inspector->getColumnInfo($this->productionSchema, 'project');
        $tempCols = $this->inspector->getColumnNames($this->tempSchema, 'project');
        $dumpProject = $loader->fetchProject(901);

        $newId = $loader->insertProject($dumpProject, $insertColumns, $colInfo, $tempCols, null);

        $this->assertGreaterThan(0, $newId);
        $this->createdProjectIds[] = $newId;

        $project = $this->db->createCommand(
            "SELECT name, user_id FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $newId]
        )->queryOne();
        $this->assertEquals('NewAutoProject', $project['name']);
        $this->assertEquals(1, (int) $project['user_id']); // user_id overridden
    }

    private function createLoader(): EntityLoader
    {
        return new EntityLoader(
            $this->db,
            $this->inspector,
            $this->tempSchema,
            $this->productionSchema,
            1
        );
    }

    private function insertTempProject(int $id, string $name, int $userId): void
    {
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`project`",
            [
                'id' => $id,
                'user_id' => $userId,
                'name' => $name,
                'description' => 'Test project',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
    }
}
