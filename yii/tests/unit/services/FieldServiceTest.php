<?php

namespace tests\unit\services;

use app\models\Field;
use app\models\Project;
use app\models\ProjectLinkedProject;
use app\services\FieldService;
use Codeception\Test\Unit;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\db\Connection;

class FieldServiceTest extends Unit
{
    private FieldService $service;

    private Connection $db;

    protected function _before(): void
    {
        parent::_before();

        $this->db = new Connection([
            'dsn' => 'sqlite::memory:',
        ]);
        $this->db->open();
        Yii::$app->set('db', $this->db);

        $this->createSchema();
        $this->seedData();

        $this->service = new FieldService();
    }

    protected function _after(): void
    {
        parent::_after();
        $this->db->close();
    }

    public function testFetchFieldsMapGeneral(): void
    {
        $result = $this->service->fetchFieldsMap(1, null);

        $expected = [
            'GEN:{{codeBlock}}' => [
                'id' => 1,
                'label' => 'codeBlock',
                'isProjectSpecific' => false,
            ],
            'GEN:{{extraCriteria}}' => [
                'id' => 3,
                'label' => 'extraCriteria',
                'isProjectSpecific' => false,
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testFetchFieldsMapProjectSpecific(): void
    {
        $result = $this->service->fetchFieldsMap(1, 1);

        $expected = [
            'PRJ:{{codeType}}' => [
                'id' => 2,
                'label' => 'codeType',
                'isProjectSpecific' => true,
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testSaveFieldWithOptionsReturnsFalseWhenFieldValidationFails(): void
    {
        /** @var Field&MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['validate', 'save'])
            ->getMock();

        $field->populateRelation('fieldOptions', []);

        $field
            ->expects($this->once())
            ->method('validate')
            ->willReturn(false);

        $field
            ->expects($this->never())
            ->method('save');

        $result = $this->service->saveFieldWithOptions($field, []);

        $this->assertFalse($result);
    }

    public function testSaveFieldWithOptionsSuccessWithoutOptions(): void
    {
        /** @var Field&MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['validate', 'save'])
            ->getMock();

        $field->populateRelation('fieldOptions', []);

        $field
            ->expects($this->once())
            ->method('validate')
            ->willReturn(true);

        $field
            ->expects($this->once())
            ->method('save')
            ->with(false)
            ->willReturn(true);

        $result = $this->service->saveFieldWithOptions($field, []);

        $this->assertTrue($result);
    }

    public function testSaveFieldWithOptionsReturnsFalseWhenFieldSaveFails(): void
    {
        /** @var Field&MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['validate', 'save'])
            ->getMock();

        $field->populateRelation('fieldOptions', []);

        $field
            ->expects($this->once())
            ->method('validate')
            ->willReturn(true);

        $field
            ->expects($this->once())
            ->method('save')
            ->with(false)
            ->willReturn(false);

        $result = $this->service->saveFieldWithOptions($field, []);

        $this->assertFalse($result);
    }

    public function testDeleteFieldSuccess(): void
    {
        /** @var Field&MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['delete'])
            ->getMock();

        $field
            ->expects($this->once())
            ->method('delete')
            ->willReturn(1);

        $result = $this->service->deleteField($field);

        $this->assertTrue($result);
    }

    public function testDeleteFieldReturnsFalseWhenDeleteThrows(): void
    {
        /** @var Field&MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['delete'])
            ->getMock();

        $field
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new Exception('Delete failed'));

        $result = $this->service->deleteField($field);

        $this->assertFalse($result);
    }

    public function testFetchExternalFieldsMapReturnsEmptyWhenProjectIdMissing(): void
    {
        $result = $this->service->fetchExternalFieldsMap(1, null);

        $this->assertSame([], $result);
    }

    public function testFetchExternalFieldsMapIncludesSharedFieldsFromLinkedProjects(): void
    {
        $result = $this->service->fetchExternalFieldsMap(1, 2);

        $this->assertSame([
            'EXT:{{LP: extField}}' => [
                'id' => 5,
                'label' => 'LP: External Field',
                'isProjectSpecific' => true,
            ],
        ], $result);
    }

    public function testFetchExternalFieldsMapExcludesNonSharedFields(): void
    {
        $result = $this->service->fetchExternalFieldsMap(1, 2);

        $this->assertArrayNotHasKey('EXT:{{LP: privateField}}', $result);
    }

    private function createSchema(): void
    {
        $this->db->createCommand()->createTable(Project::tableName(), [
            'id' => 'pk',
            'user_id' => 'integer NOT NULL',
            'name' => 'string NOT NULL',
            'label' => 'string',
            'description' => 'text',
            'prompt_instance_copy_format' => "string NOT NULL DEFAULT 'md'",
            'created_at' => 'integer',
            'updated_at' => 'integer',
            'deleted_at' => 'integer',
        ])->execute();

        $this->db->createCommand()->createTable(Field::tableName(), [
            'id' => 'pk',
            'user_id' => 'integer NOT NULL',
            'project_id' => 'integer',
            'name' => 'string NOT NULL',
            'type' => 'string NOT NULL',
            'content' => 'text',
            'selected_by_default' => 'integer NOT NULL DEFAULT 0',
            'share' => 'integer NOT NULL DEFAULT 0',
            'label' => 'string',
            'created_at' => 'integer',
            'updated_at' => 'integer',
        ])->execute();

        $this->db->createCommand()->createTable(ProjectLinkedProject::tableName(), [
            'id' => 'pk',
            'project_id' => 'integer NOT NULL',
            'linked_project_id' => 'integer NOT NULL',
            'created_at' => 'integer',
            'updated_at' => 'integer',
        ])->execute();
    }

    private function seedData(): void
    {
        $timestamp = 1737766798;

        $projects = [
            [
                'id' => 1,
                'user_id' => 100,
                'name' => 'Test Project',
                'label' => null,
                'description' => 'A test project',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'name' => 'Test Project 2',
                'label' => 'TP2',
                'description' => 'Test project 2',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 3,
                'user_id' => 1,
                'name' => 'Linked Project',
                'label' => 'LP',
                'description' => 'Linked project',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($projects as $project) {
            $this->db->createCommand()->insert(Project::tableName(), $project)->execute();
        }

        $fields = [
            [
                'id' => 1,
                'user_id' => 1,
                'project_id' => null,
                'name' => 'codeBlock',
                'type' => 'text',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 0,
                'label' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'project_id' => 1,
                'name' => 'codeType',
                'type' => 'select',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 0,
                'label' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 3,
                'user_id' => 1,
                'project_id' => null,
                'name' => 'extraCriteria',
                'type' => 'multi-select',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 0,
                'label' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 4,
                'user_id' => 100,
                'project_id' => null,
                'name' => 'unitTest',
                'type' => 'text',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 0,
                'label' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 5,
                'user_id' => 1,
                'project_id' => 3,
                'name' => 'extField',
                'type' => 'text',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 1,
                'label' => 'External Field',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => 6,
                'user_id' => 1,
                'project_id' => 3,
                'name' => 'privateField',
                'type' => 'text',
                'content' => null,
                'selected_by_default' => 0,
                'share' => 0,
                'label' => 'Private Field',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($fields as $field) {
            $this->db->createCommand()->insert(Field::tableName(), $field)->execute();
        }

        $this->db->createCommand()->insert(ProjectLinkedProject::tableName(), [
            'id' => 1,
            'project_id' => 2,
            'linked_project_id' => 3,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->execute();
    }
}
