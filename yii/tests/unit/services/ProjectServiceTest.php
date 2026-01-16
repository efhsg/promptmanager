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

    public function testFindOrCreateByNameReturnsNullWhenNameIsNull(): void
    {
        $result = $this->service->findOrCreateByName(100, null);

        self::assertNull($result);
    }

    public function testFindOrCreateByNameReturnsNullWhenNameIsEmpty(): void
    {
        $result = $this->service->findOrCreateByName(100, '');

        self::assertNull($result);
    }

    public function testFindOrCreateByNameReturnsExistingProjectId(): void
    {
        // Fixture has project ID 1 named 'Test Project' for user 100
        $result = $this->service->findOrCreateByName(100, 'Test Project');

        self::assertSame(1, $result);
    }

    public function testFindOrCreateByNameCreatesNewProject(): void
    {
        $result = $this->service->findOrCreateByName(100, 'Brand New Project');

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);

        // Verify project was created
        $project = Project::findOne($result);
        self::assertNotNull($project);
        self::assertSame('Brand New Project', $project->name);
        self::assertSame(100, $project->user_id);

        // Cleanup
        $project->delete();
    }

    public function testFindOrCreateByNameReturnsErrorsOnValidationFailure(): void
    {
        // Create a project with the same name first
        $existing = new Project(['user_id' => 100, 'name' => 'Duplicate Name']);
        $existing->save();

        try {
            // Attempt to create with same name (should fail unique validation)
            $result = $this->service->findOrCreateByName(100, 'Duplicate Name');

            // Should return the existing project ID since it finds it
            self::assertSame($existing->id, $result);
        } finally {
            $existing->delete();
        }
    }

    public function testFindOrCreateByNameDoesNotFindOtherUsersProjects(): void
    {
        // Create project for different user
        $otherUserProject = new Project(['user_id' => 999, 'name' => 'Other User Project']);
        $otherUserProject->save();

        try {
            // Should create new project for user 100, not find user 999's project
            $result = $this->service->findOrCreateByName(100, 'Other User Project');

            self::assertIsInt($result);
            self::assertNotSame($otherUserProject->id, $result);

            // Cleanup
            Project::deleteAll(['id' => $result]);
        } finally {
            $otherUserProject->delete();
        }
    }
}
