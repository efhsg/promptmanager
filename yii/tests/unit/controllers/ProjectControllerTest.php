<?php

namespace tests\unit\controllers;

use app\controllers\ProjectController;
use app\models\Project;
use app\modules\identity\models\User;
use app\services\ClaudeCliService;
use app\services\EntityPermissionService;
use app\services\ProjectService;
use Codeception\Test\Unit;
use Yii;

class ProjectControllerTest extends Unit
{
    private const TEST_USER_ID = 996;

    protected function _before(): void
    {
        Project::deleteAll(['user_id' => self::TEST_USER_ID]);
    }

    protected function _after(): void
    {
        Project::deleteAll(['user_id' => self::TEST_USER_ID]);
    }

    public function testClaudeCommandsReturnsEmptyWhenNoRootDirectory(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => null,
        ]);
        $project->save(false);

        $controller = $this->createController();

        $result = $controller->actionClaudeCommands($project->id);

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['commands']);
    }

    public function testClaudeCommandsReturnsCommandList(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => '/some/path',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')
            ->with('/some/path')
            ->willReturn([
                'deploy' => 'Deploy app',
                'review' => 'Review code',
            ]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionClaudeCommands($project->id);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['commands']);
        $this->assertSame('Deploy app', $result['commands']['deploy']);
        $this->assertSame('Review code', $result['commands']['review']);
    }

    public function testClaudeCommandsReturnsEmptyWhenNoneFound(): void
    {
        $this->mockAuthenticatedUser(self::TEST_USER_ID);

        $project = new Project([
            'user_id' => self::TEST_USER_ID,
            'name' => 'Test Project',
            'root_directory' => '/nonexistent/path',
        ]);
        $project->save(false);

        $mockClaudeService = $this->createMock(ClaudeCliService::class);
        $mockClaudeService->method('loadCommandsFromDirectory')->willReturn([]);

        $controller = $this->createControllerWithClaudeService($mockClaudeService);

        $result = $controller->actionClaudeCommands($project->id);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['commands']);
    }

    private function createController(): ProjectController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $projectService = Yii::$container->get(ProjectService::class);
        $claudeCliService = new ClaudeCliService();

        return new ProjectController(
            'project',
            Yii::$app,
            $permissionService,
            $projectService,
            $claudeCliService
        );
    }

    private function createControllerWithClaudeService(ClaudeCliService $claudeService): ProjectController
    {
        $permissionService = Yii::$container->get(EntityPermissionService::class);
        $projectService = Yii::$container->get(ProjectService::class);

        return new ProjectController(
            'project',
            Yii::$app,
            $permissionService,
            $projectService,
            $claudeService
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
}
