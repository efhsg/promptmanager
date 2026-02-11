<?php

namespace tests\unit\services\projectload;

use app\services\projectload\PlaceholderRemapper;
use Codeception\Test\Unit;
use Yii;

/**
 * Tests for PlaceholderRemapper — PRJ/GEN/EXT placeholder remapping.
 *
 * Does NOT use _fixtures() because DDL statements (CREATE DATABASE)
 * implicitly commit the transaction, breaking Codeception's transaction-based cleanup.
 */
class PlaceholderRemapperTest extends Unit
{
    private string $tempSchema;
    private string $productionSchema;

    public function _before(): void
    {
        $this->productionSchema = Yii::$app->db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->tempSchema = 'yii_load_temp_remap_' . getmypid();

        // Ensure user_id=1 exists for fallback-by-name test
        $this->ensureUserExists(1, 'testuser_remap');

        // Ensure a global field 'codeBlock' exists for user 1
        $this->ensureGlobalFieldExists(1, 'codeBlock', 'text');

        // Drop + recreate to ensure clean state
        Yii::$app->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
        Yii::$app->db->createCommand(
            "CREATE DATABASE `{$this->tempSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )->execute();

        Yii::$app->db->createCommand(
            "CREATE TABLE `{$this->tempSchema}`.`field` LIKE `{$this->productionSchema}`.`field`"
        )->execute();
        Yii::$app->db->createCommand(
            "CREATE TABLE `{$this->tempSchema}`.`project` LIKE `{$this->productionSchema}`.`project`"
        )->execute();
    }

    public function _after(): void
    {
        Yii::$app->db->createCommand("DROP DATABASE IF EXISTS `{$this->tempSchema}`")->execute();
    }

    public function testRemapPrjPlaceholderWithMapping(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([42 => 100, 43 => 101]);

        $body = json_encode([
            'ops' => [
                ['insert' => 'Hello PRJ:{{42}} and PRJ:{{43}} world'],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        $this->assertEquals('Hello PRJ:{{100}} and PRJ:{{101}} world', $result['ops'][0]['insert']);
    }

    public function testRemapPrjPlaceholderWithoutMappingKeepsOriginal(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([42 => 100]);

        $body = json_encode([
            'ops' => [
                ['insert' => 'Missing PRJ:{{99}}'],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        $this->assertEquals('Missing PRJ:{{99}}', $result['ops'][0]['insert']);
        $this->assertNotEmpty($remapper->getWarnings());
    }

    public function testRemapGenPlaceholderWithMapping(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setGlobalFieldMap([10 => 200]);

        $body = json_encode([
            'ops' => [
                ['insert' => 'GEN:{{10}} content'],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        $this->assertEquals('GEN:{{200}} content', $result['ops'][0]['insert']);
    }

    public function testRemapGenPlaceholderFallsBackToLocalByName(): void
    {
        // Insert a field in temp schema that maps to a local global field
        Yii::$app->db->createCommand()->insert(
            "`{$this->tempSchema}`.`field`",
            [
                'id' => 999,
                'user_id' => 99,
                'project_id' => null,
                'name' => 'codeBlock',
                'type' => 'text',
                'share' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        )->execute();

        // Find the local 'codeBlock' global field ID
        $localCodeBlockId = (int)Yii::$app->db->createCommand(
            "SELECT id FROM `{$this->productionSchema}`.`field`
             WHERE name = 'codeBlock' AND project_id IS NULL AND user_id = 1"
        )->queryScalar();
        $this->assertGreaterThan(0, $localCodeBlockId);

        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setGlobalFieldMap([]); // No mapping

        $body = json_encode([
            'ops' => [
                ['insert' => 'GEN:{{999}} content'],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        // Should resolve to local codeBlock field
        $this->assertEquals("GEN:{{{$localCodeBlockId}}} content", $result['ops'][0]['insert']);
    }

    public function testRemapInvalidJsonReturnsOriginal(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);

        $result = $remapper->remap('not json');
        $this->assertEquals('not json', $result);
    }

    public function testRemapDeltaWithoutOpsReturnsOriginal(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);

        $body = json_encode(['other' => 'data']);
        $result = $remapper->remap($body);
        $this->assertEquals($body, $result);
    }

    public function testRemapFieldIdForProjectField(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([50 => 150]);

        $this->assertEquals(150, $remapper->remapFieldId(50));
    }

    public function testRemapFieldIdForGlobalField(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setGlobalFieldMap([60 => 160]);

        $this->assertEquals(160, $remapper->remapFieldId(60));
    }

    public function testRemapFieldIdReturnsNullWhenNotFound(): void
    {
        // Field does not exist in temp schema
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([]);
        $remapper->setGlobalFieldMap([]);

        $this->assertNull($remapper->remapFieldId(9999));
    }

    public function testClearWarningsResetsWarningList(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([]);

        $body = json_encode(['ops' => [['insert' => 'PRJ:{{999}}']]]);
        $remapper->remap($body);
        $this->assertNotEmpty($remapper->getWarnings());

        $remapper->clearWarnings();
        $this->assertEmpty($remapper->getWarnings());
    }

    public function testRemapMultiplePlaceholderTypesInSingleOp(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([10 => 100]);
        $remapper->setGlobalFieldMap([20 => 200]);

        $body = json_encode([
            'ops' => [
                ['insert' => 'PRJ:{{10}} and GEN:{{20}} mixed'],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        $this->assertEquals('PRJ:{{100}} and GEN:{{200}} mixed', $result['ops'][0]['insert']);
    }

    public function testRemapPreservesNonStringOps(): void
    {
        $remapper = new PlaceholderRemapper(Yii::$app->db, $this->tempSchema, 1);
        $remapper->setProjectFieldMap([10 => 100]);

        $body = json_encode([
            'ops' => [
                ['insert' => ['image' => 'data:image/png']],
                ['insert' => 'PRJ:{{10}} text'],
                ['insert' => 1],
            ],
        ]);

        $result = json_decode($remapper->remap($body), true);
        $this->assertEquals(['image' => 'data:image/png'], $result['ops'][0]['insert']);
        $this->assertEquals('PRJ:{{100}} text', $result['ops'][1]['insert']);
        $this->assertEquals(1, $result['ops'][2]['insert']);
    }

    private function ensureUserExists(int $id, string $username): void
    {
        $exists = (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`user` WHERE id = :id",
            [':id' => $id]
        )->queryScalar();
        if ($exists === 0) {
            Yii::$app->db->createCommand()->insert("`{$this->productionSchema}`.`user`", [
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
        $exists = (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM `{$this->productionSchema}`.`field`
             WHERE name = :name AND project_id IS NULL AND user_id = :userId",
            [':name' => $name, ':userId' => $userId]
        )->queryScalar();
        if ($exists === 0) {
            Yii::$app->db->createCommand()->insert("`{$this->productionSchema}`.`field`", [
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
}
