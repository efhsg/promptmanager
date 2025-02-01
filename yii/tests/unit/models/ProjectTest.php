<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace tests\unit\models;

use app\models\Project;
use app\modules\identity\models\User;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ProjectTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    public function testFindProjectById()
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();
        verify($project->name)->equals('Test Project');

        verify(Project::findOne(999))->empty();
    }

    public function testProjectBelongsToUser()
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $user = $project->user;
        verify($user)->notEmpty();
        verify($user->id)->equals($project->user_id);
    }

    public function testValidateProject()
    {
        // Test valid project
        $project = new Project();
        $project->name = 'New Project';
        $project->user_id = 1;
        verify($project->validate())->true();

        // Test missing name (required)
        $project = new Project();
        $project->user_id = 1;
        verify($project->validate())->false();
        verify(array_key_exists('name', $project->errors))->true();

        // Test maximum name length (255 characters)
        $project = new Project();
        $project->name = str_repeat('a', 255);
        $project->user_id = 1;
        verify($project->validate())->true();

        // Test exceeding name length
        $project->name = str_repeat('a', 256);
        verify($project->validate())->false();
    }

    public function testTimestampsAreUpdatedOnSave()
    {
        // Create a new project and save it
        $project = new Project();
        $project->name = 'Timestamp Test Project';
        $project->user_id = 1;
        verify($project->save())->true();

        $originalCreatedAt = $project->created_at;
        $originalUpdatedAt = $project->updated_at;
        sleep(1); // ensure time difference

        // Update a property and save again
        $project->name = 'Updated Timestamp Test Project';
        verify($project->save())->true();

        // Verify that created_at remains the same and updated_at is newer
        verify($project->created_at)->equals($originalCreatedAt);
        verify($project->updated_at)->greaterThan($originalUpdatedAt);
    }

    public function testSoftDeleteProject()
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $project->deleted_at = time();
        verify($project->save())->true();

        $softDeletedProject = Project::findOne(1);
        verify($softDeletedProject)->notEmpty();
        verify($softDeletedProject->deleted_at)->notEmpty();
    }

    public function testRestoreSoftDeletedProject()
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $project->deleted_at = null;
        verify($project->save())->true();

        $restoredProject = Project::findOne(1);
        verify($restoredProject)->notEmpty();
        verify($restoredProject->deleted_at)->empty();
    }

    public function testFindOnlyActiveProjects()
    {
        $activeProject = new Project();
        $activeProject->name = 'Active Project';
        $activeProject->user_id = 1;
        verify($activeProject->save())->true();

        $softDeletedProject = new Project();
        $softDeletedProject->name = 'Soft Deleted Project';
        $softDeletedProject->user_id = 1;
        $softDeletedProject->deleted_at = time();
        verify($softDeletedProject->save())->true();

        $activeProjects = Project::find()->where(['deleted_at' => null])->all();
        verify($activeProjects)->notEmpty();

        /** @var Project $project */
        foreach ($activeProjects as $project) {
            verify($project->deleted_at)->empty();
        }
    }

    public function testProjectFailsValidationWithInvalidUserId()
    {
        $project = new Project();
        $project->name = 'Invalid User Project';
        $project->user_id = 99999;

        verify($project->validate())->false();
        verify(array_key_exists('user_id', $project->errors))->true();
    }

    public function testProjectRelationWithUserIsValid()
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        /** @var User $user */
        $user = $project->getUser()->one();
        verify($user)->notEmpty();
        verify($user->id)->equals($project->user_id);
    }


}
