<?php

namespace tests\unit\services\projectload;

use app\services\projectload\DumpImporter;
use app\services\projectload\ProjectLoadService;
use app\services\projectload\SchemaInspector;
use Codeception\Test\Unit;
use RuntimeException;
use Yii;
use yii\db\Connection;

/**
 * Functional tests for ProjectLoadService.
 *
 * Does NOT use _fixtures() because DDL statements (CREATE DATABASE)
 * implicitly commit the transaction, breaking Codeception's transaction-based cleanup.
 * Instead, ensures required users/fields exist manually and cleans up in _after().
 */
class ProjectLoadServiceTest extends Unit
{
    private Connection $db;
    private ProjectLoadService $service;
    private SchemaInspector $inspector;
    private DumpImporter $importer;
    private string $productionSchema;
    private string $tempSchema;
    private string $tempDir;

    /** @var int[] IDs of projects created during test (for cleanup) */
    private array $createdProjectIds = [];

    /** @var int[] IDs of global fields created during test (for cleanup) */
    private array $createdFieldIds = [];

    public function _before(): void
    {
        $this->db = Yii::$app->db;
        $this->inspector = new SchemaInspector($this->db);
        $this->importer = new DumpImporter($this->db, $this->inspector);
        $this->service = new ProjectLoadService($this->db, $this->importer, $this->inspector);
        $this->productionSchema = $this->inspector->getProductionSchema();
        $this->createdProjectIds = [];
        $this->createdFieldIds = [];

        // Ensure required users exist (DDL below breaks transaction, so no fixtures)
        $this->ensureUserExists(1, 'testuser_svctest');
        $this->ensureUserExists(100, 'testuser_svctest_100');

        // Ensure a global field 'codeBlock' exists for user 1 (needed for reuse test)
        $this->ensureGlobalFieldExists(1, 'codeBlock', 'text');

        $this->tempDir = sys_get_temp_dir() . '/projectload_service_test_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0o777, true);
        }

        // Create temp schema for dump data
        $this->tempSchema = 'yii_load_temp_svctest_' . getmypid();
        $this->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
        $this->db->createCommand(
            "CREATE DATABASE `{$this->tempSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        $this->copyTableStructures();
    }

    public function _after(): void
    {
        // Clean up created fields
        foreach ($this->createdFieldIds as $id) {
            $this->db->createCommand(
                "DELETE FROM `{$this->productionSchema}`.`field` WHERE id = :id",
                [':id' => $id]
            )->execute();
        }

        // Clean up created projects (reverse order for FK safety)
        foreach (array_reverse($this->createdProjectIds) as $id) {
            // Delete prompt_instances first (ON DELETE RESTRICT)
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

        // Drop temp schema
        if (isset($this->tempSchema)) {
            $this->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
        }

        // Remove temp files
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            @rmdir($this->tempDir);
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

    private function ensureGlobalFieldExists(int $userId, string $name, string $type): void
    {
        $exists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = :userId",
            [':name' => $name, ':userId' => $userId]
        )->queryScalar();
        if ($exists === 0) {
            $this->db->createCommand()->insert("`{$this->productionSchema}`.`field`", [
                'user_id' => $userId,
                'project_id' => null,
                'name' => $name,
                'type' => $type,
                'share' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
    }

    // ────────────────────────────────────────────────────────────────
    // List tests
    // ────────────────────────────────────────────────────────────────

    public function testListProjectsShowsProjectsInDump(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $result = $this->service->listProjects($dumpFile);

        $this->assertArrayHasKey('projects', $result);
        $this->assertArrayHasKey('entityCounts', $result);
        $this->assertNotEmpty($result['projects']);

        $projectIds = array_column($result['projects'], 'id');
        $this->assertContains('500', $projectIds);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: new project (no local match)
    // ────────────────────────────────────────────────────────────────

    public function testLoadNewProjectCreatesProjectWithChildren(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1);

        $this->assertEquals(1, $report->getSuccessCount());
        $this->assertEquals(0, $report->getErrorCount());
        $this->assertEquals(1, $report->getNewCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->assertNotNull($newProjectId);
        $this->createdProjectIds[] = $newProjectId;

        // Verify project was created
        $project = $this->db->createCommand(
            "SELECT * FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $newProjectId]
        )->queryOne();
        $this->assertNotFalse($project);
        $this->assertEquals('DumpProject', $project['name']);
        $this->assertEquals(1, (int) $project['user_id']);
        $this->assertNull($project['root_directory']);
        $this->assertNull($project['ai_options']);

        // Verify children
        $contextCount = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`context` WHERE project_id = :id",
            [':id' => $newProjectId]
        )->queryScalar();
        $this->assertEquals(1, $contextCount);

        $fieldCount = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`field` WHERE project_id = :id",
            [':id' => $newProjectId]
        )->queryScalar();
        $this->assertGreaterThanOrEqual(1, $fieldCount);

        $templateCount = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`prompt_template` WHERE project_id = :id",
            [':id' => $newProjectId]
        )->queryScalar();
        $this->assertEquals(1, $templateCount);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: existing project (name match — replacement)
    // ────────────────────────────────────────────────────────────────

    public function testLoadExistingProjectReplacesData(): void
    {
        $this->skipIfMysqlCliMissing();

        // Create an expendable local project (not fixture-managed)
        $localId = $this->createExpendableProject('ReplacableProject', 1);
        $this->createdProjectIds[] = $localId;

        // Insert dump project with the same name
        $this->insertDumpProject(600, 'ReplacableProject', 99);
        $this->insertDumpContext(600, 600, 'New Context From Dump');
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [600], 1);

        $this->assertEquals(1, $report->getSuccessCount());
        $this->assertEquals(1, $report->getReplacementCount());

        // Verify the local project was replaced
        $project = $this->db->createCommand(
            "SELECT * FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $localId]
        )->queryOne();
        $this->assertNotFalse($project);
        $this->assertEquals('ReplacableProject', $project['name']);
        $this->assertEquals(1, (int) $project['user_id']);

        // New context should be present
        $contexts = $this->db->createCommand(
            "SELECT name FROM `{$this->productionSchema}`.`context` WHERE project_id = :id",
            [':id' => $localId]
        )->queryColumn();
        $this->assertContains('New Context From Dump', $contexts);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: explicit local-project-ids matching
    // ────────────────────────────────────────────────────────────────

    public function testLoadWithLocalProjectIdsUsesExplicitMapping(): void
    {
        $this->skipIfMysqlCliMissing();

        // Create expendable project
        $localId = $this->createExpendableProject('ExplicitMatchTarget', 1);
        $this->createdProjectIds[] = $localId;

        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1, false, false, [$localId]);

        $this->assertEquals(1, $report->getSuccessCount());
        $this->assertEquals(1, $report->getReplacementCount());

        // Verify project now has dump data with preserved ID
        $project = $this->db->createCommand(
            "SELECT name FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $localId]
        )->queryOne();
        $this->assertEquals('DumpProject', $project['name']);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: dry-run
    // ────────────────────────────────────────────────────────────────

    public function testDryRunDoesNotModifyData(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $countBefore = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`project`"
        )->queryScalar();

        $report = $this->service->load($dumpFile, [500], 1, true);

        $countAfter = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`project`"
        )->queryScalar();

        $this->assertEquals($countBefore, $countAfter);

        $projects = $report->getProjects();
        $this->assertEquals('dry-run', $projects[500]['status']);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: soft-deleted project in dump
    // ────────────────────────────────────────────────────────────────

    public function testLoadSkipsSoftDeletedProjectInDump(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->insertDumpProject(700, 'DeletedProject', 99, '2025-01-01 00:00:00');
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [700], 1);

        $this->assertEquals(1, $report->getSkippedCount());
        $this->assertEquals(0, $report->getSuccessCount());
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: project not found in dump
    // ────────────────────────────────────────────────────────────────

    public function testLoadSkipsMissingProjectInDump(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [99999], 1);

        $this->assertEquals(1, $report->getSkippedCount());
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: placeholder remapping
    // ────────────────────────────────────────────────────────────────

    public function testLoadRemapsProjectFieldPlaceholders(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->insertDumpField(501, 500, 'testField', 'text', 99);
        $templateBody = json_encode(['ops' => [['insert' => 'Hello PRJ:{{501}} world']]]);
        $this->insertDumpTemplate(501, 500, 'PlaceholderTemplate', $templateBody);
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        $newTemplateId = $report->getMappedId('prompt_template', 501);
        $this->assertNotNull($newTemplateId);

        $template = $this->db->createCommand(
            "SELECT template_body FROM `{$this->productionSchema}`.`prompt_template` WHERE id = :id",
            [':id' => $newTemplateId]
        )->queryOne();

        $this->assertNotFalse($template);

        $body = json_decode($template['template_body'], true);
        $this->assertNotEmpty($body['ops']);
        $insertText = $body['ops'][0]['insert'];
        $this->assertStringNotContainsString('PRJ:{{501}}', $insertText);
        $this->assertMatchesRegularExpression('/PRJ:\{\{\d+\}\}/', $insertText);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: global fields
    // ────────────────────────────────────────────────────────────────

    public function testLoadWithIncludeGlobalFieldsCreatesNewGlobalField(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->insertDumpField(800, null, 'uniqueGlobalTestField_' . getmypid(), 'text', 99);
        $templateBody = json_encode(['ops' => [['insert' => 'Use GEN:{{800}}']]]);
        $this->insertDumpTemplate(502, 500, 'GlobalFieldTemplate', $templateBody);
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1, false, true);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        // Verify the global field was created
        $fieldName = 'uniqueGlobalTestField_' . getmypid();
        $globalField = $this->db->createCommand(
            "SELECT * FROM `{$this->productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = 1",
            [':name' => $fieldName]
        )->queryOne();
        $this->assertNotFalse($globalField);

        // Clean up the global field
        $this->db->createCommand(
            "DELETE FROM `{$this->productionSchema}`.`field` WHERE id = :id",
            [':id' => $globalField['id']]
        )->execute();
    }

    public function testLoadWithIncludeGlobalFieldsDiscoverFromTemplateField(): void
    {
        $this->skipIfMysqlCliMissing();

        // Insert a global field in the dump that is NOT referenced in template body GEN:{{}} placeholders,
        // but IS referenced in the template_field junction table
        $globalFieldName = 'uniqueGlobalTfTestField_' . getmypid();
        $this->insertDumpField(810, null, $globalFieldName, 'text', 99);
        $this->insertDumpTemplate(510, 500, 'TemplateWithGlobalTf', '{"ops":[{"insert":"no placeholders\\n"}]}');
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`template_field`",
            [
                'template_id' => 510,
                'field_id' => 810,
            ]
        )->execute();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1, false, true);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        // Verify the global field was created
        $globalField = $this->db->createCommand(
            "SELECT * FROM `{$this->productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = 1",
            [':name' => $globalFieldName]
        )->queryOne();
        $this->assertNotFalse($globalField);
        $this->createdFieldIds[] = (int) $globalField['id'];

        // Verify the template_field was inserted (not skipped)
        $newTemplateId = $report->getMappedId('prompt_template', 510);
        $this->assertNotNull($newTemplateId);

        $tfCount = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`template_field`
             WHERE template_id = :tid AND field_id = :fid",
            [':tid' => $newTemplateId, ':fid' => $globalField['id']]
        )->queryScalar();
        $this->assertEquals(1, $tfCount);
    }

    public function testLoadWithoutIncludeGlobalFieldsResolvesExistingGlobalFieldInTemplateField(): void
    {
        $this->skipIfMysqlCliMissing();

        // Insert a global field in the dump that maps to existing local 'codeBlock'
        $this->insertDumpField(820, null, 'codeBlock', 'text', 99);
        $this->insertDumpTemplate(520, 500, 'TemplateWithExistingGlobalTf', '{"ops":[{"insert":"no placeholders\\n"}]}');
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`template_field`",
            [
                'template_id' => 520,
                'field_id' => 820,
            ]
        )->execute();
        $dumpFile = $this->createDumpFileFromTempSchema();

        // Load WITHOUT --include-global-fields — remapFieldId should still resolve by name
        $report = $this->service->load($dumpFile, [500], 1, false, false);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        // Find local codeBlock field
        $localCodeBlockId = (int) $this->db->createCommand(
            "SELECT id FROM `{$this->productionSchema}`.`field`
             WHERE name = 'codeBlock' AND project_id IS NULL AND user_id = 1"
        )->queryScalar();

        // Verify the template_field was inserted with the local field ID (not skipped)
        $newTemplateId = $report->getMappedId('prompt_template', 520);
        $this->assertNotNull($newTemplateId);

        $tfCount = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`template_field`
             WHERE template_id = :tid AND field_id = :fid",
            [':tid' => $newTemplateId, ':fid' => $localCodeBlockId]
        )->queryScalar();
        $this->assertEquals(1, $tfCount);
    }

    public function testLoadWithIncludeGlobalFieldsReusesExistingField(): void
    {
        $this->skipIfMysqlCliMissing();

        // Find the local 'codeBlock' global field ID (ensured in _before)
        $localCodeBlockId = (int) $this->db->createCommand(
            "SELECT id FROM `{$this->productionSchema}`.`field`
             WHERE name = 'codeBlock' AND project_id IS NULL AND user_id = 1"
        )->queryScalar();
        $this->assertGreaterThan(0, $localCodeBlockId);

        $this->insertDumpField(801, null, 'codeBlock', 'text', 99);
        $templateBody = json_encode(['ops' => [['insert' => 'Use GEN:{{801}}']]]);
        $this->insertDumpTemplate(503, 500, 'ExistingGlobalTemplate', $templateBody);
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1, false, true);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        // Global field should map to the existing local codeBlock field
        $mappedId = $report->getMappedId('field', 801);
        $this->assertEquals($localCodeBlockId, $mappedId);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: linked projects
    // ────────────────────────────────────────────────────────────────

    public function testLoadSkipsLinkWhenLinkedProjectNotFound(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`project_linked_project`",
            [
                'project_id' => 500,
                'linked_project_id' => 99999,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        $projects = $report->getProjects();
        $warnings = $projects[500]['warnings'];
        $this->assertNotEmpty($warnings);

        $linkWarning = array_filter($warnings, fn($w) => str_contains($w, 'link') || str_contains($w, 'Gelinkt'));
        $this->assertNotEmpty($linkWarning);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: validation errors
    // ────────────────────────────────────────────────────────────────

    public function testLoadThrowsWhenNoProjectIdsProvided(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Geen project-IDs');
        $this->service->load($dumpFile, [], 1);
    }

    public function testLoadThrowsWhenUserNotFound(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('niet gevonden');
        $this->service->load($dumpFile, [500], 99999);
    }

    public function testLoadThrowsWhenLocalProjectIdsMismatchCount(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('moet gelijk zijn');
        $this->service->load($dumpFile, [500], 1, false, false, [1, 2]);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: transaction rollback on error
    // ────────────────────────────────────────────────────────────────

    public function testLoadErrorsWhenLocalProjectBelongsToDifferentUser(): void
    {
        $this->skipIfMysqlCliMissing();

        // Create expendable project for user 100
        $localId = $this->createExpendableProject('WrongUserProject', 100);
        $this->createdProjectIds[] = $localId;

        $dumpFile = $this->createDumpFileFromTempSchema();

        // Try to load with user_id=1 but local project belongs to user 100
        $report = $this->service->load($dumpFile, [500], 1, false, false, [$localId]);

        $this->assertEquals(1, $report->getErrorCount());
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: user_id override
    // ────────────────────────────────────────────────────────────────

    public function testLoadedProjectGetsTargetUserId(): void
    {
        $this->skipIfMysqlCliMissing();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 100);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        $project = $this->db->createCommand(
            "SELECT user_id FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $newProjectId]
        )->queryOne();
        $this->assertEquals(100, (int) $project['user_id']);
    }

    // ────────────────────────────────────────────────────────────────
    // Load tests: excluded columns
    // ────────────────────────────────────────────────────────────────

    public function testLoadedProjectHasExcludedColumnsNull(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->db->createCommand(
            "UPDATE `{$this->tempSchema}`.`project`
             SET root_directory = '/some/path',
                 ai_options = '{\"test\": true}',
                 ai_context = '{\"ops\": []}'
             WHERE id = 500"
        )->execute();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1);

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        $project = $this->db->createCommand(
            "SELECT root_directory, ai_options, ai_context
             FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $newProjectId]
        )->queryOne();

        $this->assertNull($project['root_directory']);
        $this->assertNull($project['ai_options']);
        $this->assertNull($project['ai_context']);
    }

    // ────────────────────────────────────────────────────────────────
    // Cleanup tests
    // ────────────────────────────────────────────────────────────────

    public function testCleanupRemovesOrphanedSchemas(): void
    {
        $orphanedSchema = 'yii_load_temp_orphan_svc_' . getmypid();
        $this->db->createCommand(
            "CREATE DATABASE IF NOT EXISTS `{$orphanedSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        $cleaned = $this->service->cleanup();

        $this->assertArrayHasKey($orphanedSchema, $cleaned);
    }

    // ────────────────────────────────────────────────────────────────
    // Multiple projects in single run
    // ────────────────────────────────────────────────────────────────

    public function testLoadMultipleProjectsInSingleRun(): void
    {
        $this->skipIfMysqlCliMissing();

        $this->insertDumpProject(501, 'SecondDumpProject', 99);
        $this->insertDumpContext(601, 501, 'Second Context');
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500, 501], 1);

        $this->assertEquals(2, $report->getSuccessCount());
        $this->assertEquals(2, $report->getNewCount());

        $this->createdProjectIds[] = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $report->getMappedId('project', 501);
    }

    // ────────────────────────────────────────────────────────────────
    // Label conflict
    // ────────────────────────────────────────────────────────────────

    public function testLoadSetsLabelNullOnConflict(): void
    {
        $this->skipIfMysqlCliMissing();

        // Create a local project with label 'CONFLICT_LBL'
        $localId = $this->createExpendableProject('ConflictLabelProject', 1, 'CONFLICT_LBL');
        $this->createdProjectIds[] = $localId;

        // Set dump project to same label
        $this->db->createCommand(
            "UPDATE `{$this->tempSchema}`.`project` SET label = 'CONFLICT_LBL' WHERE id = 500"
        )->execute();
        $dumpFile = $this->createDumpFileFromTempSchema();

        $report = $this->service->load($dumpFile, [500], 1);

        $this->assertEquals(1, $report->getSuccessCount());

        $newProjectId = $report->getMappedId('project', 500);
        $this->createdProjectIds[] = $newProjectId;

        $project = $this->db->createCommand(
            "SELECT label FROM `{$this->productionSchema}`.`project` WHERE id = :id",
            [':id' => $newProjectId]
        )->queryOne();
        $this->assertNull($project['label']);
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Creates an expendable local project (not managed by fixtures) for replacement tests.
     */
    private function createExpendableProject(string $name, int $userId, ?string $label = null): int
    {
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'description' => 'Expendable test project',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($label !== null) {
            $data['label'] = $label;
        }

        $this->db->createCommand()->insert(
            "`{$this->productionSchema}`.`project`",
            $data
        )->execute();

        return (int) $this->db->getLastInsertID();
    }

    private function copyTableStructures(): void
    {
        $tables = ['project', 'context', 'field', 'field_option', 'prompt_template',
            'template_field', 'prompt_instance', 'note', 'project_linked_project'];

        foreach ($tables as $table) {
            $this->db->createCommand(
                "CREATE TABLE IF NOT EXISTS `{$this->tempSchema}`.`{$table}` LIKE `{$this->productionSchema}`.`{$table}`"
            )->execute();
        }

        // Insert default dump project data
        $this->insertDumpProject(500, 'DumpProject', 99);
        $this->insertDumpContext(500, 500, 'DumpContext');
        $this->insertDumpField(500, 500, 'dumpField', 'text', 99);
        $this->insertDumpTemplate(500, 500, 'DumpTemplate', '{"ops":[{"insert":"test\\n"}]}');
    }

    private function insertDumpProject(int $id, string $name, int $userId, ?string $deletedAt = null): void
    {
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`project`",
            [
                'id' => $id,
                'user_id' => $userId,
                'name' => $name,
                'description' => 'Test dump project',
                'deleted_at' => $deletedAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
    }

    private function insertDumpContext(int $id, int $projectId, string $name): void
    {
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`context`",
            [
                'id' => $id,
                'project_id' => $projectId,
                'name' => $name,
                'content' => 'Dump context content',
                'share' => 0,
                'is_default' => 0,
                'order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
    }

    private function insertDumpField(int $id, ?int $projectId, string $name, string $type, int $userId): void
    {
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`field`",
            [
                'id' => $id,
                'user_id' => $userId,
                'project_id' => $projectId,
                'name' => $name,
                'type' => $type,
                'content' => null,
                'share' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
    }

    private function insertDumpTemplate(int $id, int $projectId, string $name, string $templateBody): void
    {
        $this->db->createCommand()->insert(
            "`{$this->tempSchema}`.`prompt_template`",
            [
                'id' => $id,
                'project_id' => $projectId,
                'name' => $name,
                'template_body' => $templateBody,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();
    }

    private function createDumpFileFromTempSchema(): string
    {
        $dsn = $this->parseDsn();
        $dumpFile = $this->tempDir . '/test_dump.sql';

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --no-create-db --skip-add-locks --skip-lock-tables %s > %s 2>&1',
            escapeshellarg($dsn['host']),
            escapeshellarg($dsn['port']),
            escapeshellarg($this->db->username),
            escapeshellarg($this->db->password),
            escapeshellarg($this->tempSchema),
            escapeshellarg($dumpFile)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('mysqldump failed: ' . implode("\n", $output));
        }

        return $dumpFile;
    }

    /**
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

    private function skipIfMysqlCliMissing(): void
    {
        exec('which mysql 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('mysql CLI binary not available');
        }
        exec('which mysqldump 2>/dev/null', $output2, $exitCode2);
        if ($exitCode2 !== 0) {
            $this->markTestSkipped('mysqldump CLI binary not available');
        }
    }
}
