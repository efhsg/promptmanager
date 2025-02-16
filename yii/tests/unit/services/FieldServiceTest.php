<?php namespace tests\unit\services;

use app\services\FieldService;
use Codeception\Test\Unit;
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
        $this->service = new FieldService();
    }

    public function testFetchFieldsMapGeneral(): void
    {
        $result = $this->service->fetchFieldsMap(1, null);
        $expected = [
            'GEN:{{codeBlock}}' => ['id' => 1, 'label' => 'codeBlock', 'isProjectSpecific' => false],
            'GEN:{{extraCriteria}}' => ['id' => 3, 'label' => 'extraCriteria', 'isProjectSpecific' => false],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testFetchFieldsMapProjectSpecific(): void
    {
        $result = $this->service->fetchFieldsMap(1, 1);
        $expected = [
            'PRJ:{{codeType}}' => ['id' => 2, 'label' => 'codeType', 'isProjectSpecific' => true],
        ];
        $this->assertEquals($expected, $result);
    }
}
