<?php

namespace tests\unit\controllers;

use app\controllers\ScratchPadController;
use app\models\Project;
use app\models\ScratchPad;
use app\modules\identity\models\User;
use app\services\EntityPermissionService;
use app\services\YouTubeTranscriptService;
use Codeception\Test\Unit;
use RuntimeException;
use Yii;
use yii\web\Request;

class ScratchPadControllerTest extends Unit
{
    private const TEST_USER_ID = 998;
    private const OTHER_USER_ID = 997;

    protected function _before(): void
    {
        ScratchPad::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
    }

    protected function _after(): void
    {
        ScratchPad::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
    }

    public function testImportYoutubeCreatesScratcPadSuccessfully(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['videoId' => 'dQw4w9WgXcQ']);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $mockService->method('fetchTranscript')->willReturn([
            'title' => 'Test Video',
            'channel' => 'Test Channel',
            'transcript' => 'Hello world',
        ]);
        $mockService->method('convertToQuillDelta')->willReturn('{"ops":[{"insert":"Hello world\n"}]}');
        $mockService->method('getTitle')->willReturn('Test Video');

        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);

        $scratchPad = ScratchPad::findOne($result['id']);
        $this->assertNotNull($scratchPad);
        $this->assertSame('Test Video', $scratchPad->name);
        $this->assertSame(self::TEST_USER_ID, $scratchPad->user_id);
    }

    public function testImportYoutubeWithOwnedProjectSucceeds(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'My Project',
        ]);
        $project->save(false);

        $this->mockJsonRequest([
            'videoId' => 'dQw4w9WgXcQ',
            'project_id' => $project->id,
        ]);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $mockService->method('fetchTranscript')->willReturn(['title' => 'Video', 'transcript' => 'Text']);
        $mockService->method('convertToQuillDelta')->willReturn('{"ops":[{"insert":"Text\n"}]}');
        $mockService->method('getTitle')->willReturn('Video');

        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertTrue($result['success']);

        $scratchPad = ScratchPad::findOne($result['id']);
        $this->assertSame($project->id, $scratchPad->project_id);
    }

    public function testImportYoutubeRejectsUnownedProject(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->ensureUserExists(self::OTHER_USER_ID);

        $otherUserProject = new Project([
            'user_id' => self::OTHER_USER_ID,
            'name' => 'Other User Project',
        ]);
        $otherUserProject->save(false);

        $this->mockJsonRequest([
            'videoId' => 'dQw4w9WgXcQ',
            'project_id' => $otherUserProject->id,
        ]);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('project_id', $result['errors']);
    }

    public function testImportYoutubeRejectsNonExistentProject(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'videoId' => 'dQw4w9WgXcQ',
            'project_id' => 99999,
        ]);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('project_id', $result['errors']);
    }

    public function testImportYoutubeRejectsEmptyVideoId(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['videoId' => '']);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('videoId', $result['errors']);
    }

    public function testImportYoutubeHandlesServiceException(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['videoId' => 'dQw4w9WgXcQ']);

        $mockService = $this->createMock(YouTubeTranscriptService::class);
        $mockService->method('fetchTranscript')
            ->willThrowException(new RuntimeException('Transcripts are disabled for this video.'));

        $controller = $this->createController($mockService);

        $result = $controller->actionImportYoutube();

        $this->assertFalse($result['success']);
        $this->assertSame('Transcripts are disabled for this video.', $result['message']);
    }

    private function createController(YouTubeTranscriptService $youtubeService): ScratchPadController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);

        return new ScratchPadController(
            'scratch-pad',
            Yii::$app,
            $permissionService,
            $youtubeService
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
        $reflection = new \ReflectionClass($request);

        $rawBodyProperty = $reflection->getProperty('_rawBody');
        $rawBodyProperty->setAccessible(true);
        $rawBodyProperty->setValue($request, json_encode($data));

        // Mock isPost
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}
