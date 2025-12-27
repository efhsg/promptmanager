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

            $this->assertCount(2, $result);
            $this->assertSame('Test Project', $result[1]);
            $this->assertSame('Additional Project', $result[(int) $project->id]);
        } finally {
            $project->delete();
        }
    }

    public function testFetchProjectsListOrdersProjectsByName(): void
    {
        $alphaProject = new Project();
        $alphaProject->user_id = 100;
        $alphaProject->name = 'Alpha Project';

        $zetaProject = new Project();
        $zetaProject->user_id = 100;
        $zetaProject->name = 'Zeta Project';

        $this->assertSame(true, $alphaProject->save());
        $this->assertSame(true, $zetaProject->save());

        try {
            $result = $this->service->fetchProjectsList(100);

            $this->assertSame(
                [
                    'Alpha Project',
                    'Test Project',
                    'Zeta Project',
                ],
                array_values($result)
            );
        } finally {
            $alphaProject->delete();
            $zetaProject->delete();
        }
    }
}
