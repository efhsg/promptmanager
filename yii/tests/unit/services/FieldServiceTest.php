<?php

namespace tests\unit\services;

use app\models\Field;
use app\services\FieldService;
use Codeception\Test\Unit;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FieldFixture;
use tests\fixtures\ProjectFixture;

class FieldServiceTest extends Unit
{
    protected FieldService $service;

    public function _fixtures(): array
    {
        return [
            'fields' => FieldFixture::class,
            'projects' => ProjectFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new FieldService();
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

        $this->assertEquals($expected, $result);
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

        $this->assertEquals($expected, $result);
    }

    public function testSaveFieldWithOptionsReturnsFalseWhenFieldValidationFails(): void
    {
        /** @var Field|MockObject $field */
        $field = $this
            ->getMockBuilder(Field::class)
            ->onlyMethods(['validate', 'save'])
            ->getMock();

        // Ensure fieldOptions is an array, not null
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
        /** @var Field|MockObject $field */
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
        /** @var Field|MockObject $field */
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
        /** @var Field|MockObject $field */
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
        /** @var Field|MockObject $field */
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
}
