<?php

/** @noinspection PhpUnused */

namespace tests\unit\services;

use app\services\ModelService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

class ModelServiceTest extends Unit
{
    private ModelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ModelServiceTestActiveRecordStub::reset();
        $this->service = new ModelService();
    }

    protected function tearDown(): void
    {
        ModelServiceTestActiveRecordStub::reset();
        parent::tearDown();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function testFindModelByIdReturnsModel(): void
    {
        /** @var ActiveRecord&MockObject $model */
        $model = $this->createActiveRecordMock();
        ModelServiceTestActiveRecordStub::$findOneResult = $model;

        $result = $this->service->findModelById(5, ModelServiceTestActiveRecordStub::class);

        $this->assertSame($model, $result);
        $this->assertSame(['id' => 5], ModelServiceTestActiveRecordStub::$findOneCondition);
    }

    public function testFindModelByIdThrowsWhenModelNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service->findModelById(404, ModelServiceTestActiveRecordStub::class);
    }

    public function testFindModelsByAttributesReturnsMatches(): void
    {
        /** @var ActiveRecord&MockObject $firstModel */
        $firstModel = $this->createActiveRecordMock();
        /** @var ActiveRecord&MockObject $secondModel */
        $secondModel = $this->createActiveRecordMock();
        ModelServiceTestActiveRecordStub::$findAllResult = [$firstModel, $secondModel];

        $result = $this->service->findModelsByAttributes(['status' => 1], ModelServiceTestActiveRecordStub::class);

        $this->assertSame([$firstModel, $secondModel], $result);
        $this->assertSame(['status' => 1], ModelServiceTestActiveRecordStub::$findAllCondition);
    }

    public function testDeleteModelSafelyReturnsTrueOnSuccessfulDelete(): void
    {
        /** @var ActiveRecord&MockObject $model */
        $model = $this->createActiveRecordMock(['delete']);
        $model->expects($this->once())->method('delete')->willReturn(1);

        $this->assertTrue($this->service->deleteModelSafely($model));
    }

    public function testDeleteModelSafelyReturnsFalseWhenDeleteThrows(): void
    {
        /** @var ActiveRecord&MockObject $model */
        $model = $this->createActiveRecordMock(['delete']);
        $model->expects($this->once())->method('delete')->willThrowException(new RuntimeException('fail'));

        $this->assertFalse($this->service->deleteModelSafely($model));
    }

    public function testDeleteModelsByAttributesReturnsDeletedCount(): void
    {
        ModelServiceTestActiveRecordStub::$deleteAllResult = 3;

        $result = $this->service->deleteModelsByAttributes(['type' => 'old'], ModelServiceTestActiveRecordStub::class);

        $this->assertSame(3, $result);
        $this->assertSame(['type' => 'old'], ModelServiceTestActiveRecordStub::$deleteAllCondition);
    }

    /**
     * @param list<string> $methods
     * @return ActiveRecord&MockObject
     */
    private function createActiveRecordMock(array $methods = []): ActiveRecord
    {
        $builder = $this->getMockBuilder(ActiveRecord::class)
            ->disableOriginalConstructor();

        if ($methods !== []) {
            $builder->onlyMethods($methods);
        }

        /** @var ActiveRecord&MockObject $mock */
        $mock = $builder->getMock();

        return $mock;
    }
}

final class ModelServiceTestActiveRecordStub
{
    public static ?ActiveRecord $findOneResult = null;

    public static ?array $findOneCondition = null;

    /** @var ActiveRecord[] */
    public static array $findAllResult = [];

    public static ?array $findAllCondition = null;

    public static int $deleteAllResult = 0;

    public static ?array $deleteAllCondition = null;

    public static function reset(): void
    {
        self::$findOneResult = null;
        self::$findOneCondition = null;
        self::$findAllResult = [];
        self::$findAllCondition = null;
        self::$deleteAllResult = 0;
        self::$deleteAllCondition = null;
    }

    public static function tableName(): string
    {
        return 'model_service_test';
    }

    public static function findOne(array $condition): ?ActiveRecord
    {
        self::$findOneCondition = $condition;

        return self::$findOneResult;
    }

    public static function findAll(array $condition): array
    {
        self::$findAllCondition = $condition;

        return self::$findAllResult;
    }

    public static function deleteAll(array $condition): int
    {
        self::$deleteAllCondition = $condition;

        return self::$deleteAllResult;
    }

}
