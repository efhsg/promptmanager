<?php

namespace tests\unit\services;

use app\models\Project;
use app\services\ProjectService;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ProjectServiceTest extends Unit
{
    private ProjectService $service;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectService();
    }

    public function testFetchProjectsListReturnsProjectsForUser(): void
    {
        $result = $this->service->fetchProjectsList(100);

        $this->assertSame([1 => 'Test Project'], $result);
    }

    public function testFetchProjectsListReturnsEmptyForUserWithoutProjects(): void
    {
        $result = $this->service->fetchProjectsList(999);

        $this->assertSame([], $result);
    }

    public function testFetchProjectsListIncludesMultipleProjectsForUser(): void
    {
        $project = new Project();
        $project->user_id = 100;
        $project->name = 'Additional Project';

        $this->assertSame(true, $project->save());

        try {
            $result = $this->service->fetchProjectsList(100);

            $this->assertSame(
                [
                    1 => 'Test Project',
                    (int)$project->id => 'Additional Project',
                ],
                $result
            );
        } finally {
            $project->delete();
        }
    }
}
