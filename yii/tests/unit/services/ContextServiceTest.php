<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace tests\unit\services;

use app\models\Context;
use app\models\Project;
use app\services\ContextService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\ContextFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ContextServiceTest extends Unit
{
    private ContextService $service;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new ContextService();
    }

    public function testSaveContextPersistsModel(): void
    {
        /** @var Context&MockObject $model */
        $model = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $model->expects($this->once())->method('save')->willReturn(true);

        $this->assertTrue($this->service->saveContext($model));
    }

    public function testDeleteContextRemovesModel(): void
    {
        /** @var Context&MockObject $model */
        $model = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $model->expects($this->once())->method('delete')->willReturn(1);

        $this->assertTrue($this->service->deleteContext($model));
    }

    public function testSaveContextPropagatesFailure(): void
    {
        /** @var Context&MockObject $model */
        $model = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $model->expects($this->once())->method('save')->willReturn(false);

        $this->assertFalse($this->service->saveContext($model));
    }

    public function testDeleteContextReturnsFalseOnFailure(): void
    {
        /** @var Context&MockObject $model */
        $model = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $model->expects($this->once())->method('delete')->willReturn(false);

        $this->assertFalse($this->service->deleteContext($model));
    }

    public function testFetchContextsReturnsUserContextsSorted(): void
    {
        $result = $this->service->fetchContexts(100);

        $this->assertSame(
            [
                1 => 'Test Context',
                3 => 'Test Context3',
            ],
            $result
        );
        $names = array_values($result);
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
    }

    public function testFetchContextsReturnsEmptyForUnknownUser(): void
    {
        $result = $this->service->fetchContexts(999);

        $this->assertSame([], $result);
    }

    public function testFetchContextsContentReturnsUserContextContent(): void
    {
        $result = $this->service->fetchContextsContent(100);

        $this->assertSame(
            [
                1 => 'This is a test context',
                3 => 'This is a second test context',
            ],
            $result
        );
    }

    public function testFetchContextsContentReturnsEmptyForUnknownUser(): void
    {
        $result = $this->service->fetchContextsContent(999);

        $this->assertSame([], $result);
    }

    public function testFetchProjectContextsFiltersByProject(): void
    {
        $result = $this->service->fetchProjectContexts(100, 1);

        $this->assertSame(
            [
                1 => 'Test Context',
                3 => 'Test Context3',
            ],
            $result
        );
    }

    public function testFetchProjectContextsReturnsAllWhenProjectIdIsNull(): void
    {
        $result = $this->service->fetchProjectContexts(100, null);

        $this->assertSame(
            [
                1 => 'Test Context',
                3 => 'Test Context3',
            ],
            $result
        );
    }

    public function testFetchProjectContextsReturnsEmptyForUnknownUser(): void
    {
        $result = $this->service->fetchProjectContexts(999, 1);

        $this->assertSame([], $result);
    }

    public function testFetchContextsContentByIdReturnsEmptyWhenNoIds(): void
    {
        $result = $this->service->fetchContextsContentById(100, []);

        $this->assertSame([], $result);
    }

    public function testFetchContextsContentByIdFiltersByIds(): void
    {
        $result = $this->service->fetchContextsContentById(100, [3, 2]);

        $this->assertSame(
            [
                3 => 'This is a second test context',
            ],
            $result
        );
    }

    public function testFetchContextsContentByIdReturnsEmptyForNonExistingIds(): void
    {
        $result = $this->service->fetchContextsContentById(100, [999]);

        $this->assertSame([], $result);
    }

    public function testFetchDefaultContextIdsReturnsDefaultsForUserAndProject(): void
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Default Context';
        $context->content = 'default';
        $context->is_default = 1;
        $this->assertTrue($context->save());

        $result = $this->service->fetchDefaultContextIds(100, 1);

        $this->assertSame([$context->id], array_map('intval', $result));

        $context->delete();
    }

    public function testFetchDefaultContextIdsReturnsDefaultsAcrossProjectsWhenProjectIsNull(): void
    {
        $project = new Project();
        $project->user_id = 100;
        $project->name = 'Another Project';
        $project->root_directory = '/tmp';
        $this->assertTrue($project->save(false));

        $contextOne = new Context();
        $contextOne->project_id = 1;
        $contextOne->name = 'Default One';
        $contextOne->content = 'default';
        $contextOne->is_default = 1;
        $this->assertTrue($contextOne->save(false));

        $contextTwo = new Context();
        $contextTwo->project_id = $project->id;
        $contextTwo->name = 'Default Two';
        $contextTwo->content = 'default';
        $contextTwo->is_default = 1;
        $this->assertTrue($contextTwo->save(false));

        $result = $this->service->fetchDefaultContextIds(100, null);

        $resultInts = array_map('intval', $result);
        sort($resultInts);

        $expected = [$contextOne->id, $contextTwo->id];
        sort($expected);
        $this->assertSame($expected, $resultInts);

        $contextOne->delete();
        $contextTwo->delete();
        $project->delete();
    }

    public function testFetchDefaultContextIdsReturnsEmptyForOtherUser(): void
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Other User Default';
        $context->content = 'default';
        $context->is_default = 1;
        $this->assertTrue($context->save());

        $result = $this->service->fetchDefaultContextIds(999, null);

        $this->assertSame([], $result);

        $context->delete();
    }
}
