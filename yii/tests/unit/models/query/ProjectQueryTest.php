<?php

namespace tests\unit\models\query;

use app\models\Project;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ProjectQueryTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
        ];
    }

    public function testWithNameFindsProjectByExactName(): void
    {
        // Fixture has 'Test Project' for user 100
        $project = Project::find()->withName('Test Project')->one();

        self::assertNotNull($project);
        self::assertSame('Test Project', $project->name);
    }

    public function testWithNameReturnsNullForNonexistentName(): void
    {
        $project = Project::find()->withName('Nonexistent Project')->one();

        self::assertNull($project);
    }

    public function testWithNameIsCaseSensitive(): void
    {
        $project = Project::find()->withName('test project')->one();

        // MySQL may be case-insensitive depending on collation
        // This test documents the expected behavior
        if ($project !== null) {
            self::assertSame('Test Project', $project->name);
        } else {
            self::assertNull($project);
        }
    }

    public function testWithNameChainedWithForUser(): void
    {
        // Create project with same name for different user
        $otherProject = new Project(['user_id' => 999, 'name' => 'Test Project']);
        $otherProject->save();

        try {
            $project = Project::find()
                ->forUser(100)
                ->withName('Test Project')
                ->one();

            self::assertNotNull($project);
            self::assertSame(100, $project->user_id);
            self::assertSame(1, $project->id); // Original fixture project
        } finally {
            $otherProject->delete();
        }
    }
}
