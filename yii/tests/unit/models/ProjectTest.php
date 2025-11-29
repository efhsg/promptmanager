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

    /**
     * @dataProvider rootDirectoryProvider
     */
    public function testRootDirectoryValidation(?string $rootDirectory, bool $isValid)
    {
        $project = new Project();
        $project->name = 'Root Directory Validation';
        $project->user_id = 1;
        $project->root_directory = $rootDirectory;

        $result = $project->validate();
        verify($result)->equals($isValid);

        if ($isValid) {
            verify(array_key_exists('root_directory', $project->errors))->false();
        } else {
            verify(array_key_exists('root_directory', $project->errors))->true();
        }
    }

    public static function rootDirectoryProvider(): array
    {
        return [
            'null value' => [null, true],
            'unix path' => ['/var/www/project', true],
            'unix path trailing slash' => ['/home/erwin/projects/promptmanager/', true],
            'windows drive' => ['C:\\Projects\\Sample', true],
            'windows root' => ['C:\\', true],
            'unc path' => ['\\\\wsl$\\Ubuntu\\home\\erwin\\projects\\promptmanager', true],
            'invalid character' => ['invalid|path', false],
            'at character' => ['@', false],
            'windows illegal char' => ['C:\\Projects\\Sam?ple', false],
            'trailing invalid char' => ['C:\\invalid|', false],
        ];
    }

    public function testBlacklistedDirectoriesAreNormalized(): void
    {
        $project = new Project();
        $project->name = 'Blacklist Normalization';
        $project->user_id = 1;
        $project->blacklisted_directories = ' vendor , /runtime/logs/,web , web ';

        verify($project->validate())->true();
        $rules = $project->getBlacklistedDirectories();
        verify($rules)->equals([
            ['path' => 'vendor', 'exceptions' => []],
            ['path' => 'runtime/logs', 'exceptions' => []],
            ['path' => 'web', 'exceptions' => []],
        ]);
    }

    public function testBlacklistedDirectoriesValidationBlocksTraversal(): void
    {
        $project = new Project();
        $project->name = 'Invalid Blacklist';
        $project->user_id = 1;
        $project->blacklisted_directories = '../secrets';

        verify($project->validate())->false();
        verify($project->getErrors('blacklisted_directories'))->notEmpty();
    }

    public function testBlacklistedDirectoriesWithWhitelistExceptions(): void
    {
        $project = new Project();
        $project->name = 'Blacklist With Exceptions';
        $project->user_id = 1;
        $project->blacklisted_directories = 'vendor, web/[css,js], tests/_output';

        verify($project->validate())->true();
        $rules = $project->getBlacklistedDirectories();
        verify($rules)->equals([
            ['path' => 'vendor', 'exceptions' => []],
            ['path' => 'web', 'exceptions' => ['css', 'js']],
            ['path' => 'tests/_output', 'exceptions' => []],
        ]);
    }

    public function testBlacklistedDirectoriesNormalizeWhitelistExceptions(): void
    {
        $project = new Project();
        $project->name = 'Normalize Exceptions';
        $project->user_id = 1;
        $project->blacklisted_directories = 'web/[css , js, css]';

        verify($project->validate())->true();
        $rules = $project->getBlacklistedDirectories();
        verify($rules)->equals([
            ['path' => 'web', 'exceptions' => ['css', 'js']],
        ]);
    }

    public function testBlacklistedDirectoriesValidationRejectsEmptyException(): void
    {
        $project = new Project();
        $project->name = 'Invalid Exception';
        $project->user_id = 1;
        $project->blacklisted_directories = 'web/[css,,js]';

        verify($project->validate())->false();
        verify($project->getErrors('blacklisted_directories'))->notEmpty();
    }

    public function testBlacklistedDirectoriesValidationRejectsNestedExceptions(): void
    {
        $project = new Project();
        $project->name = 'Invalid Nested Exception';
        $project->user_id = 1;
        $project->blacklisted_directories = 'web/[css/nested]';

        verify($project->validate())->false();
        verify($project->getErrors('blacklisted_directories'))->notEmpty();
    }

    public function testBlacklistedDirectoriesRoundTrip(): void
    {
        $project = new Project();
        $project->name = 'Round Trip Test';
        $project->user_id = 1;
        $project->blacklisted_directories = 'vendor, web/[css,js], tests/_output';

        verify($project->validate())->true();
        verify($project->save())->true();

        $reloaded = Project::findOne($project->id);
        verify($reloaded->blacklisted_directories)->equals('vendor,web/[css,js],tests/_output');

        $rules = $reloaded->getBlacklistedDirectories();
        verify($rules)->equals([
            ['path' => 'vendor', 'exceptions' => []],
            ['path' => 'web', 'exceptions' => ['css', 'js']],
            ['path' => 'tests/_output', 'exceptions' => []],
        ]);
    }
}
