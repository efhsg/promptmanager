<?php

namespace tests\unit\controllers;

use app\controllers\ClaudeController;
use app\handlers\ClaudeQuickHandler;
use app\models\ClaudeRun;
use app\models\Project;
use app\modules\identity\models\User;
use app\services\ClaudeCliService;
use app\services\ClaudeRunCleanupService;
use app\services\ClaudeStreamRelayService;
use app\services\EntityPermissionService;
use Codeception\Test\Unit;
use ReflectionClass;
use Yii;
use yii\web\NotFoundHttpException;

class ClaudeControllerTest extends Unit
{
    private const TEST_USER_ID = 995;
    private const OTHER_USER_ID = 994;

    protected function _before(): void
    {
        ClaudeRun::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
    }

    protected function _after(): void
    {
        ClaudeRun::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
        Project::deleteAll(['user_id' => [self::TEST_USER_ID, self::OTHER_USER_ID]]);
    }

    // ---------------------------------------------------------------
    // actionCheckConfig tests
    // ---------------------------------------------------------------

    public function testCheckConfigReturnsErrorWhenNoRootDirectory(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => null,
        ]);
        $project->save(false);

        $controller = $this->createControllerWithClaudeService(new ClaudeCliService());

        $result = $controller->actionCheckConfig($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Project has no root directory configured.', $result['error']);
    }

    public function testCheckConfigReturnsConfigStatus(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => '/some/path',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('checkClaudeConfigForPath')->willReturn([
            'hasCLAUDE_MD' => true,
            'hasClaudeDir' => false,
            'hasAnyConfig' => true,
            'pathStatus' => 'has_config',
            'pathMapped' => false,
            'requestedPath' => '/some/path',
            'effectivePath' => '/some/path',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionCheckConfig($project->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['hasCLAUDE_MD']);
        $this->assertFalse($result['hasClaudeDir']);
        $this->assertTrue($result['hasAnyConfig']);
        $this->assertSame('has_config', $result['pathStatus']);
    }

    public function testCheckConfigIncludesPromptManagerContextStatus(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => '/some/path',
            'claude_context' => '## Custom Context',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('checkClaudeConfigForPath')->willReturn([
            'hasCLAUDE_MD' => false,
            'hasClaudeDir' => false,
            'hasAnyConfig' => false,
            'pathStatus' => 'no_config',
            'pathMapped' => false,
            'requestedPath' => '/some/path',
            'effectivePath' => '/some/path',
        ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionCheckConfig($project->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['hasAnyConfig']);
        $this->assertSame('no_config', $result['pathStatus']);
        $this->assertTrue($result['hasPromptManagerContext']);
    }

    // ---------------------------------------------------------------
    // actionSummarizeSession tests
    // ---------------------------------------------------------------

    public function testSummarizeSessionReturnsSuccessWithValidConversation(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

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

        $result = $controller->actionSummarizeSession($project->id);

        $this->assertTrue($result['success']);
        $this->assertSame('## Context & Goal\nTest summary', $result['summary']);
        $this->assertSame(8000, $result['duration_ms']);
        $this->assertSame('sonnet', $result['model']);
    }

    public function testSummarizeSessionReturnsErrorForEmptyConversation(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

        $this->mockJsonRequest(['conversation' => '   ']);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Conversation text is empty.', $result['error']);
    }

    public function testSummarizeSessionReturnsErrorWhenClaudeFails(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

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

        $result = $controller->actionSummarizeSession($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame('CLI process timed out after 120s', $result['error']);
    }

    public function testSummarizeSessionReturnsErrorWhenOutputIsEmpty(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

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

        $result = $controller->actionSummarizeSession($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Summarization returned empty output.', $result['error']);
    }

    public function testSummarizeSessionPassesSonnetModelAndPlanMode(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

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
        $controller->actionSummarizeSession($project->id);

        $this->assertSame('sonnet', $capturedOptions['model']);
        $this->assertSame('plan', $capturedOptions['permissionMode']);
        $this->assertArrayHasKey('appendSystemPrompt', $capturedOptions);
        $this->assertStringContainsString('conversation summarizer', $capturedOptions['appendSystemPrompt']);
        $this->assertNull($capturedSessionId);
    }

    public function testSummarizeSessionReturnsErrorForNonArrayRequest(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

        $this->mockRawBody('"just a string"');

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionSummarizeSession($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid request format.', $result['error']);
    }

    // ---------------------------------------------------------------
    // actionSuggestName tests
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // actionSummarizePrompt tests
    // ---------------------------------------------------------------

    public function testSummarizePromptPersistsTitleToRunWhenRunIdProvided(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

        $run = new ClaudeRun([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'prompt_markdown' => 'Some long prompt text that needs summarizing',
        ]);
        $run->save(false);

        $this->mockJsonRequest([
            'prompt' => str_repeat('a', 120),
            'runId' => $run->id,
        ]);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => 'AI-generated title',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSummarizePrompt($project->id);

        $this->assertTrue($result['success']);
        $this->assertSame('AI-generated title', $result['title']);

        $run->refresh();
        $this->assertSame('AI-generated title', $run->prompt_summary);
    }

    public function testSummarizePromptDoesNotPersistWhenRunIdMissing(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

        $run = new ClaudeRun([
            'user_id' => self::TEST_USER_ID,
            'project_id' => $project->id,
            'prompt_markdown' => 'Original summary text',
            'prompt_summary' => 'Original summary',
        ]);
        $run->save(false);

        $this->mockJsonRequest([
            'prompt' => str_repeat('a', 120),
        ]);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => 'AI-generated title',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSummarizePrompt($project->id);

        $this->assertTrue($result['success']);

        $run->refresh();
        $this->assertSame('Original summary', $run->prompt_summary);
    }

    public function testSummarizePromptIgnoresNonNumericRunId(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

        $this->mockJsonRequest([
            'prompt' => str_repeat('a', 120),
            'runId' => 'not-a-number',
        ]);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => 'AI-generated title',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSummarizePrompt($project->id);

        $this->assertTrue($result['success']);
        $this->assertSame('AI-generated title', $result['title']);
    }

    public function testSummarizePromptIgnoresRunOwnedByOtherUser(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);
        $this->ensureUserExists(self::OTHER_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);
        $otherProject = new Project([
            'user_id' => self::OTHER_USER_ID,
            'name' => 'Other Project',
        ]);
        $otherProject->save(false);

        $otherRun = new ClaudeRun([
            'user_id' => self::OTHER_USER_ID,
            'project_id' => $otherProject->id,
            'prompt_markdown' => 'Other user prompt',
            'prompt_summary' => 'Original other summary',
        ]);
        $otherRun->save(false);

        $this->mockJsonRequest([
            'prompt' => str_repeat('a', 120),
            'runId' => $otherRun->id,
        ]);

        $mockHandler = $this->createMock(ClaudeQuickHandler::class);
        $mockHandler->method('run')->willReturn([
            'success' => true,
            'output' => 'AI-generated title',
        ]);

        $controller = $this->createControllerWithQuickHandler($mockHandler);
        $result = $controller->actionSummarizePrompt($project->id);

        $this->assertTrue($result['success']);

        $otherRun->refresh();
        $this->assertSame('Original other summary', $otherRun->prompt_summary);
    }

    // ---------------------------------------------------------------
    // loadClaudeCommands tests (private method via reflection)
    // ---------------------------------------------------------------

    public function testLoadClaudeCommandsReturnsFlatListWithoutGroups(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = $this->createProject(self::TEST_USER_ID);

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

        $project = $this->createProject(self::TEST_USER_ID);

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

    // ---------------------------------------------------------------
    // beforeAction session release tests
    // ---------------------------------------------------------------

    /**
     * @dataProvider sessionFreeActionsProvider
     */
    public function testBeforeActionClosesSessionForStreamingActions(string $actionId): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $mockSession = $this->createMock(\yii\web\Session::class);
        $mockSession->expects($this->once())->method('close');
        Yii::$app->set('session', $mockSession);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $controller->detachBehaviors();

        $action = $this->createMock(\yii\base\Action::class);
        $action->id = $actionId;

        $controller->beforeAction($action);
    }

    public static function sessionFreeActionsProvider(): array
    {
        return [
            'stream' => ['stream'],
            'start-run' => ['start-run'],
            'stream-run' => ['stream-run'],
            'cancel-run' => ['cancel-run'],
            'run-status' => ['run-status'],
            'active-runs' => ['active-runs'],
        ];
    }

    public function testBeforeActionDoesNotCloseSessionForNormalActions(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $mockSession = $this->createMock(\yii\web\Session::class);
        $mockSession->expects($this->never())->method('close');
        Yii::$app->set('session', $mockSession);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $controller = $this->createControllerWithClaudeService($mockClaudeService);
        $controller->detachBehaviors();

        $action = $this->createMock(\yii\base\Action::class);
        $action->id = 'index';

        $controller->beforeAction($action);
    }

    // ---------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------

    private function invokeLoadClaudeCommands(
        ClaudeController $controller,
        ?string $rootDirectory,
        Project $project
    ): array {
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('loadClaudeCommands');
        $method->setAccessible(true);
        return $method->invoke($controller, $rootDirectory, $project);
    }

    private function createControllerWithClaudeService(ClaudeCliService $claudeService): ClaudeController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $claudeQuickHandler = $this->createMock(ClaudeQuickHandler::class);
        $streamRelayService = new ClaudeStreamRelayService();
        $cleanupService = new ClaudeRunCleanupService();

        return new ClaudeController(
            'claude',
            Yii::$app,
            $permissionService,
            $claudeService,
            $claudeQuickHandler,
            $streamRelayService,
            $cleanupService
        );
    }

    private function createControllerWithQuickHandler(ClaudeQuickHandler $quickHandler): ClaudeController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $claudeCliService = new ClaudeCliService();
        $streamRelayService = new ClaudeStreamRelayService();
        $cleanupService = new ClaudeRunCleanupService();

        return new ClaudeController(
            'claude',
            Yii::$app,
            $permissionService,
            $claudeCliService,
            $quickHandler,
            $streamRelayService,
            $cleanupService
        );
    }

    private function createProject(int $userId): Project
    {
        $project = new Project([
            'user_id' => $userId,
            'name' => 'Test Project',
        ]);
        $project->save(false);
        return $project;
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
