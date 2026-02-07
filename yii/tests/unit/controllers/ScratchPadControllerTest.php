<?php

namespace tests\unit\controllers;

use app\controllers\ScratchPadController;
use app\models\Project;
use app\models\ScratchPad;
use app\modules\identity\models\User;
use app\handlers\ClaudeQuickHandler;
use app\services\ClaudeCliService;
use app\services\EntityPermissionService;
use app\services\YouTubeTranscriptService;
use Codeception\Test\Unit;
use RuntimeException;
use Yii;
use ReflectionClass;
use yii\web\NotFoundHttpException;

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
        $claudeCliService = new ClaudeCliService();
        $claudeQuickHandler = $this->createMock(ClaudeQuickHandler::class);

        return new ScratchPadController(
            'scratch-pad',
            Yii::$app,
            $permissionService,
            $youtubeService,
            $claudeCliService,
            $claudeQuickHandler
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

        // Mock isPost
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testRunClaudeSucceedsWithValidScratchPad(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello Claude\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest(['permissionMode' => 'plan']);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('Hello Claude');
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => 'Claude response here',
            'error' => '',
            'exitCode' => 0,
            'duration_ms' => 5000,
            'model' => 'opus-4.5',
            'input_tokens' => 24500,
            'output_tokens' => 150,
            'configSource' => 'managed_workspace',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertSame('Claude response here', $result['output']);
        $this->assertSame('opus-4.5', $result['model']);
        $this->assertSame(24500, $result['input_tokens']);
        $this->assertSame(150, $result['output_tokens']);
    }

    public function testRunClaudeReturnsErrorForEmptyContent(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Empty Scratch Pad',
            'content' => '{"ops":[{"insert":"\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('');

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Scratch pad content is empty.', $result['error']);
    }

    public function testRunClaudeMergesProjectDefaultsWithRequestOptions(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->setClaudeOptions(['permissionMode' => 'plan', 'model' => 'sonnet']);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Test\n"}]}',
        ]);
        $scratchPad->save(false);

        // Request overrides model but keeps permissionMode from project
        $this->mockJsonRequest(['model' => 'opus']);

        $capturedOptions = null;
        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('Test');
        $mockClaudeService->method('execute')
            ->willReturnCallback(function ($prompt, $dir, $timeout, $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return [
                    'success' => true,
                    'output' => 'Response',
                    'error' => '',
                    'exitCode' => 0,
                ];
            });

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $controller->actionRunClaude($scratchPad->id);

        $this->assertSame('plan', $capturedOptions['permissionMode']);
        $this->assertSame('opus', $capturedOptions['model']);
    }

    public function testRunClaudeReturnsSessionId(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('Hello');
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => 'Response',
            'error' => '',
            'exitCode' => 0,
            'session_id' => 'test-session-123',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertSame('test-session-123', $result['sessionId']);
    }

    public function testRunClaudeWithFollowUpPrompt(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Original content\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'sessionId' => 'existing-session-456',
            'prompt' => 'Follow-up question here',
        ]);

        $capturedPrompt = null;
        $capturedSessionId = null;

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('Original content');
        $mockClaudeService->method('execute')
            ->willReturnCallback(function ($prompt, $dir, $timeout, $options, $project, $sessionId) use (&$capturedPrompt, &$capturedSessionId) {
                $capturedPrompt = $prompt;
                $capturedSessionId = $sessionId;
                return [
                    'success' => true,
                    'output' => 'Follow-up response',
                    'error' => '',
                    'exitCode' => 0,
                    'session_id' => 'existing-session-456',
                ];
            });

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertSame('Follow-up question here', $capturedPrompt);
        $this->assertSame('existing-session-456', $capturedSessionId);
    }

    public function testRunClaudeReturnsErrorForEmptyFollowUpPrompt(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Content\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'sessionId' => 'existing-session',
            'prompt' => '   ',
        ]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Prompt is empty.', $result['error']);
    }

    public function testClaudeActionRejectsNullProject(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => null,
            'name' => 'Global Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Claude CLI requires a project.');

        $controller->actionClaude($scratchPad->id);
    }

    public function testRunClaudeConvertsContentDeltaToMarkdown(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Original content\n"}]}',
        ]);
        $scratchPad->save(false);

        $editedDelta = '{"ops":[{"insert":"Edited content\n"}]}';

        $this->mockJsonRequest([
            'contentDelta' => $editedDelta,
        ]);

        $capturedPrompt = null;
        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')
            ->willReturnCallback(function (string $input) use (&$capturedPrompt) {
                $capturedPrompt = $input;
                return 'Edited content';
            });
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => 'Response',
            'error' => '',
            'exitCode' => 0,
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertSame($editedDelta, $capturedPrompt);
    }

    public function testRunClaudeReturnsPromptMarkdownInResponse(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')->willReturn('Hello');
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => 'Response',
            'error' => '',
            'exitCode' => 0,
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $controller->actionRunClaude($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('promptMarkdown', $result);
        $this->assertSame('Hello', $result['promptMarkdown']);
    }

    public function testRunClaudeContentDeltaPriorityOverStoredContent(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Original\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'contentDelta' => '{"ops":[{"insert":"Edited\n"}]}',
        ]);

        $convertCallCount = 0;
        $lastConvertInput = null;
        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('convertToMarkdown')
            ->willReturnCallback(function (string $input) use (&$convertCallCount, &$lastConvertInput) {
                $convertCallCount++;
                $lastConvertInput = $input;
                return 'Edited';
            });
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => 'Response',
            'error' => '',
            'exitCode' => 0,
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $controller->actionRunClaude($scratchPad->id);

        // contentDelta should be used, not stored content
        $this->assertSame(1, $convertCallCount);
        $this->assertSame('{"ops":[{"insert":"Edited\n"}]}', $lastConvertInput);
    }

    public function testSummarizeSessionReturnsSuccessWithValidConversation(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'conversation' => "## You\n\nHello\n\n---\n\n## Claude\n\nHi there",
        ]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => '## Context & Goal\nTest summary',
            'error' => '',
            'exitCode' => 0,
            'duration_ms' => 8000,
            'model' => 'sonnet',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($scratchPad->id);

        $this->assertTrue($result['success']);
        $this->assertSame('## Context & Goal\nTest summary', $result['summary']);
        $this->assertSame(8000, $result['duration_ms']);
        $this->assertSame('sonnet', $result['model']);
    }

    public function testSummarizeSessionReturnsErrorForEmptyConversation(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest(['conversation' => '   ']);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Conversation text is empty.', $result['error']);
    }

    public function testSummarizeSessionReturnsErrorWhenClaudeFails(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'conversation' => "## You\n\nHello\n\n---\n\n## Claude\n\nHi",
        ]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('execute')->willReturn([
            'success' => false,
            'output' => '',
            'error' => 'CLI process timed out after 120s',
            'exitCode' => 1,
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('CLI process timed out after 120s', $result['error']);
    }

    public function testSummarizeSessionReturnsErrorWhenOutputIsEmpty(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'conversation' => "## You\n\nHello\n\n---\n\n## Claude\n\nHi",
        ]);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('execute')->willReturn([
            'success' => true,
            'output' => '',
            'error' => '',
            'exitCode' => 0,
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Summarization returned empty output.', $result['error']);
    }

    public function testSummarizeSessionPassesSonnetModelAndPlanMode(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockJsonRequest([
            'conversation' => "## You\n\nHello\n\n---\n\n## Claude\n\nHi",
        ]);

        $capturedOptions = null;
        $capturedSessionId = null;
        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('execute')
            ->willReturnCallback(function ($prompt, $dir, $timeout, $options, $project, $sessionId) use (&$capturedOptions, &$capturedSessionId) {
                $capturedOptions = $options;
                $capturedSessionId = $sessionId;
                return [
                    'success' => true,
                    'output' => 'Summary text',
                    'error' => '',
                    'exitCode' => 0,
                ];
            });

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $controller->actionSummarizeSession($scratchPad->id);

        $this->assertSame('sonnet', $capturedOptions['model']);
        $this->assertSame('plan', $capturedOptions['permissionMode']);
        $this->assertArrayHasKey('appendSystemPrompt', $capturedOptions);
        $this->assertStringContainsString('conversation summarizer', $capturedOptions['appendSystemPrompt']);
        $this->assertNull($capturedSessionId);
    }

    public function testSummarizeSessionReturnsErrorForNonArrayRequest(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $scratchPad = new ScratchPad([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'name' => 'Test Scratch Pad',
            'content' => '{"ops":[{"insert":"Hello\n"}]}',
        ]);
        $scratchPad->save(false);

        $this->mockRawBody('"just a string"');

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($scratchPad->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid request format.', $result['error']);
    }

    public function testSuggestNameReturnsSuccessWithValidContent(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['content' => 'Help me refactor the authentication module to use JWT tokens']);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => 'JWT authentication refactoring',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSuggestName();

        $this->assertTrue($result['success']);
        $this->assertSame('JWT authentication refactoring', $result['name']);
    }

    public function testSuggestNameReturnsErrorForEmptyContent(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['content' => '']);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSuggestName();

        $this->assertFalse($result['success']);
        $this->assertSame('Content is empty.', $result['error']);
    }

    public function testSuggestNameReturnsErrorForNonStringContent(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['content' => 123]);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSuggestName();

        $this->assertFalse($result['success']);
        $this->assertSame('Content is empty.', $result['error']);
    }

    public function testSuggestNameReturnsErrorForInvalidJson(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockRawBody('not json');

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSuggestName();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid JSON data.', $result['error']);
    }

    public function testSuggestNameReturnsErrorWhenAiOutputIsEmpty(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->mockJsonRequest(['content' => 'Help me refactor the authentication module']);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => '   ',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSuggestName();

        $this->assertFalse($result['success']);
        $this->assertSame('Could not generate a name.', $result['error']);
    }

    public function testLoadClaudeCommandsReturnsFlatListWithoutGroups(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'deploy' => 'Deploy app',
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        $this->assertCount(3, $result);
        $this->assertSame('Deploy app', $result['deploy']);
    }

    public function testLoadClaudeCommandsAppliesBlacklist(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'claude_options' => json_encode(['commandBlacklist' => ['deploy', 'test']]),
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'deploy' => 'Deploy app',
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('review', $result);
        $this->assertArrayNotHasKey('deploy', $result);
        $this->assertArrayNotHasKey('test', $result);
    }

    public function testLoadClaudeCommandsAppliesGrouping(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'claude_options' => json_encode([
                'commandGroups' => [
                    'CI' => ['test', 'deploy'],
                ],
            ]),
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'deploy' => 'Deploy app',
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        $this->assertArrayHasKey('CI', $result);
        $this->assertArrayHasKey('Other', $result);
        $this->assertCount(2, $result['CI']);
        $this->assertArrayHasKey('test', $result['CI']);
        $this->assertArrayHasKey('deploy', $result['CI']);
        $this->assertCount(1, $result['Other']);
        $this->assertArrayHasKey('review', $result['Other']);
    }

    public function testLoadClaudeCommandsDropsMissingGroupedCommands(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'claude_options' => json_encode([
                'commandGroups' => [
                    'CI' => ['test', 'nonexistent'],
                ],
            ]),
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        $this->assertCount(1, $result['CI']);
        $this->assertArrayNotHasKey('nonexistent', $result['CI']);
    }

    public function testLoadClaudeCommandsAppliesBlacklistBeforeGrouping(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'claude_options' => json_encode([
                'commandBlacklist' => ['test'],
                'commandGroups' => [
                    'CI' => ['test', 'deploy'],
                ],
            ]),
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'deploy' => 'Deploy app',
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        // test is blacklisted so CI group only has deploy
        $this->assertCount(1, $result['CI']);
        $this->assertArrayHasKey('deploy', $result['CI']);
        $this->assertArrayNotHasKey('test', $result['CI']);
        $this->assertArrayHasKey('Other', $result);
        $this->assertArrayHasKey('review', $result['Other']);
    }

    public function testLoadClaudeCommandsReturnsEmptyWhenNoCommands(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        $this->assertSame([], $result);
    }

    public function testLoadClaudeCommandsDropsEmptyGroupsAfterFiltering(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'claude_options' => json_encode([
                'commandBlacklist' => ['test'],
                'commandGroups' => [
                    'CI' => ['test'],
                ],
            ]),
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([
            'review' => 'Review code',
            'test' => 'Run tests',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $result = $this->invokeLoadClaudeCommands($controller, '/some/path', $project);

        // CI group should be dropped because its only command was blacklisted
        $this->assertArrayNotHasKey('CI', $result);
        // review goes to Other
        $this->assertArrayHasKey('Other', $result);
        $this->assertArrayHasKey('review', $result['Other']);
    }

    private function invokeLoadClaudeCommands(
        ScratchPadController $controller,
        ?string $rootDirectory,
        Project $project
    ): array {
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('loadClaudeCommands');
        $method->setAccessible(true);
        return $method->invoke($controller, $rootDirectory, $project);
    }

    private function createControllerWithClaudeService(ClaudeCliService $claudeService): ScratchPadController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $youtubeService = $this->createMock(YouTubeTranscriptService::class);
        $claudeQuickHandler = $this->createMock(ClaudeQuickHandler::class);

        return new ScratchPadController(
            'scratch-pad',
            Yii::$app,
            $permissionService,
            $youtubeService,
            $claudeService,
            $claudeQuickHandler
        );
    }

    private function createControllerWithQuickHandler(ClaudeQuickHandler $quickHandler): ScratchPadController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $youtubeService = $this->createMock(YouTubeTranscriptService::class);
        $claudeCliService = new ClaudeCliService();

        return new ScratchPadController(
            'scratch-pad',
            Yii::$app,
            $permissionService,
            $youtubeService,
            $claudeCliService,
            $quickHandler
        );
    }

    private function mockRawBody(string $rawBody): void
    {
        $request = Yii::$app->request;
        $reflection = new ReflectionClass($request);

        $rawBodyProperty = $reflection->getProperty('_rawBody');
        $rawBodyProperty->setAccessible(true);
        $rawBodyProperty->setValue($request, $rawBody);

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}
