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

    /**
     * @dataProvider projectByIdProvider
     */
    public function testFindProjectById(int $projectId, ?string $expectedName): void
    {
        $project = Project::findOne($projectId);

        if ($expectedName === null) {
            verify($project)->empty();
            return;
        }

        verify($project)->notEmpty();
        verify($project->name)->equals($expectedName);
    }

    public static function projectByIdProvider(): array
    {
        return [
            'existing project' => [1, 'Test Project'],
            'missing project' => [999, null],
        ];
    }

    public function testProjectBelongsToUser(): void
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $user = $project->user;
        verify($user)->notEmpty();
        verify($user->id)->equals($project->user_id);
    }

    /**
     * @dataProvider projectValidationProvider
     */
    public function testValidateProject(?string $name, ?int $userId, bool $isValid, array $expectedErrorFields): void
    {
        $project = new Project();
        $project->name = $name;
        $project->user_id = $userId;

        $result = $project->validate();
        verify($result)->equals($isValid);

        if ($isValid) {
            verify($project->errors)->empty();
            return;
        }

        verify($project->errors)->notEmpty();

        foreach ($expectedErrorFields as $field) {
            verify(array_key_exists($field, $project->errors))->true();
        }
    }

    public static function projectValidationProvider(): array
    {
        return [
            'valid project' => ['New Project', 1, true, []],
            'missing name' => [null, 1, false, ['name']],
            'maximum length' => [str_repeat('a', 255), 1, true, []],
            'exceeding length' => [str_repeat('a', 256), 1, false, ['name']],
            'invalid user' => ['Invalid User Project', 99999, false, ['user_id']],
        ];
    }

    public function testTimestampsAreUpdatedOnSave(): void
    {
        try {
            Project::setTimestampOverride(1_700_000_000);

            $project = new Project();
            $project->name = 'Timestamp Test Project';
            $project->user_id = 1;
            verify($project->save())->true();

            $originalCreatedAt = $project->created_at;
            $originalUpdatedAt = $project->updated_at;

            // Update a property and save again using a later synthetic timestamp
            Project::setTimestampOverride($originalUpdatedAt + 10);
            $project->name = 'Updated Timestamp Test Project';
            verify($project->save())->true();

            // Verify that created_at remains the same and updated_at is newer
            verify($project->created_at)->equals($originalCreatedAt);
            verify($project->updated_at)->greaterThan($originalUpdatedAt);
        } finally {
            Project::setTimestampOverride(null);
        }
    }

    public function testSoftDeleteProject(): void
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $project->deleted_at = time();
        verify($project->save())->true();

        $softDeletedProject = Project::findOne(1);
        verify($softDeletedProject)->notEmpty();
        verify($softDeletedProject->deleted_at)->notEmpty();
    }

    public function testRestoreSoftDeletedProject(): void
    {
        $project = Project::findOne(1);
        verify($project)->notEmpty();

        $project->deleted_at = null;
        verify($project->save())->true();

        $restoredProject = Project::findOne(1);
        verify($restoredProject)->notEmpty();
        verify($restoredProject->deleted_at)->empty();
    }

    public function testFindOnlyActiveProjects(): void
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

    public function testProjectRelationWithUserIsValid(): void
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
    public function testRootDirectoryValidation(?string $rootDirectory, bool $isValid): void
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

    /**
     * @dataProvider blacklistedDirectoriesProvider
     */
    public function testBlacklistedDirectoriesValidation(string $blacklistedDirectories, bool $isValid, ?array $expectedRules): void
    {
        $project = new Project();
        $project->name = 'Blacklist Validation';
        $project->user_id = 1;
        $project->blacklisted_directories = $blacklistedDirectories;

        $result = $project->validate();
        verify($result)->equals($isValid);

        if ($isValid) {
            $rules = $project->getBlacklistedDirectories();
            verify($rules)->equals($expectedRules);
            return;
        }

        verify($project->getErrors('blacklisted_directories'))->notEmpty();
    }

    public static function blacklistedDirectoriesProvider(): array
    {
        return [
            'normalized paths' => [
                'blacklistedDirectories' => ' vendor , /runtime/logs/,web , web ',
                'isValid' => true,
                'expectedRules' => [
                    ['path' => 'vendor', 'exceptions' => []],
                    ['path' => 'runtime/logs', 'exceptions' => []],
                    ['path' => 'web', 'exceptions' => []],
                ],
            ],
            'blocks traversal' => [
                'blacklistedDirectories' => '../secrets',
                'isValid' => false,
                'expectedRules' => null,
            ],
            'with whitelist exceptions' => [
                'blacklistedDirectories' => 'vendor, web/[css,js], tests/_output',
                'isValid' => true,
                'expectedRules' => [
                    ['path' => 'vendor', 'exceptions' => []],
                    ['path' => 'web', 'exceptions' => ['css', 'js']],
                    ['path' => 'tests/_output', 'exceptions' => []],
                ],
            ],
            'normalizes whitelist exceptions' => [
                'blacklistedDirectories' => 'web/[css , js, css]',
                'isValid' => true,
                'expectedRules' => [
                    ['path' => 'web', 'exceptions' => ['css', 'js']],
                ],
            ],
            'rejects empty exception' => [
                'blacklistedDirectories' => 'web/[css,,js]',
                'isValid' => false,
                'expectedRules' => null,
            ],
            'rejects nested exceptions' => [
                'blacklistedDirectories' => 'web/[css/nested]',
                'isValid' => false,
                'expectedRules' => null,
            ],
        ];
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
