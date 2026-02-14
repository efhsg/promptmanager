<?php

namespace tests\unit\controllers;

use app\controllers\NoteController;
use app\models\Project;
use app\modules\identity\models\User;
use app\services\EntityPermissionService;
use app\services\PathService;
use app\services\YouTubeTranscriptService;
use Codeception\Test\Unit;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use ReflectionClass;
use Yii;

class NoteControllerImportServerFileTest extends Unit
{
    private const TEST_USER_ID = 996;
    private const OTHER_USER_ID = 995;

    private vfsStreamDirectory $vfsRoot;

    protected function _before(): void
    {
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);

        $this->vfsRoot = vfsStream::setup('project', null, [
            'docs' => [
                'readme.md' => "# Hello\n\nThis is **bold** text.",
                'notes.txt' => 'Plain text content.',
                'script.php' => "<?php echo 'hi';",
                'empty.md' => '',
            ],
            'vendor' => [
                'autoload.php' => '<?php // autoloader',
            ],
        ]);
    }

    protected function _after(): void
    {
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
    }

    public function testImportServerFileReadsValidMarkdown(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/readme.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('importData', $result);
        $this->assertArrayHasKey('content', $result['importData']);
        $this->assertSame('readme.md', $result['filename']);

        $delta = json_decode($result['importData']['content'], true);
        $this->assertNotNull($delta);
        $this->assertArrayHasKey('ops', $delta);
    }

    public function testImportServerFileReadsPlainText(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/notes.txt',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('importData', $result);
        $this->assertSame('notes.txt', $result['filename']);

        $delta = json_decode($result['importData']['content'], true);
        $this->assertNotNull($delta);
        $this->assertSame("Plain text content.\n", $delta['ops'][0]['insert']);
    }

    public function testImportServerFileRejectsPathTraversal(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => '/../../../etc/passwd',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('/etc/passwd', $result['message']);
    }

    public function testImportServerFileRejectsBlacklistedPath(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID, 'vendor');

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'vendor/autoload.php',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
    }

    public function testImportServerFileRejectsFileTooLarge(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        vfsStream::newFile('large.md')
            ->withContent(str_repeat('x', 1048577))
            ->at($this->vfsRoot->getChild('docs'));

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/large.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('size limit', $result['message']);
    }

    public function testImportServerFileRejectsNonexistentFile(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/nonexistent.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function testImportServerFileRejectsUnownedProject(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->ensureUserExists(self::OTHER_USER_ID);
        $otherProject = $this->createProjectWithRoot(self::OTHER_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $otherProject->id,
            'path' => 'docs/readme.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid project.', $result['message']);
    }

    public function testImportServerFileRejectsNoProjectId(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $this->mockJsonRequest([]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid project.', $result['message']);
    }

    public function testImportServerFileRejectsDisallowedExtension(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/script.php',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid file type', $result['message']);
    }

    public function testImportServerFileRejectsProjectWithoutRootDirectory(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'No Root Project',
        ]);
        $project->save(false);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/readme.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid project.', $result['message']);
    }

    public function testImportServerFileHandlesEmptyFile(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $project = $this->createProjectWithRoot(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'project_id' => $project->id,
            'path' => 'docs/empty.md',
        ]);

        $controller = $this->createController();
        $result = $controller->actionImportServerFile();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('importData', $result);
    }

    private function createProjectWithRoot(int $userId, ?string $blacklist = null): Project
    {
        $project = new Project([
            'user_id' => $userId,
            'name' => 'Test Project',
            'root_directory' => $this->vfsRoot->url(),
            'blacklisted_directories' => $blacklist,
        ]);
        $project->save(false);

        return $project;
    }

    private function createController(): NoteController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $mockYoutube = $this->createMock(YouTubeTranscriptService::class);

        return new NoteController(
            'note',
            Yii::$app,
            $permissionService,
            $mockYoutube,
            pathService: new PathService()
        );
    }

    private function mockAuthenticatedUser(int $userId): void
    {
        $user = $this->ensureUserExists($userId);
        Yii::$app->user->setIdentity($user);
    }

    private function ensureUserExists(int $userId): User
    {
        $user = User::findOne($userId);
        if (!$user) {
            Yii::$app->db->createCommand()->insert('user', [
                'id' => $userId,
                'username' => 'testuser' . $userId,
                'email' => 'test' . $userId . '@example.com',
                'password_hash' => Yii::$app->security->generatePasswordHash('secret'),
                'auth_key' => Yii::$app->security->generateRandomString(),
                'status' => User::STATUS_ACTIVE,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
            $user = User::findOne($userId);
        }

        return $user;
    }

    private function mockJsonRequest(array $data): void
    {
        $request = Yii::$app->request;
        $reflection = new ReflectionClass($request);

        $rawBodyProperty = $reflection->getProperty('_rawBody');
        $rawBodyProperty->setAccessible(true);
        $rawBodyProperty->setValue($request, json_encode($data));

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}
