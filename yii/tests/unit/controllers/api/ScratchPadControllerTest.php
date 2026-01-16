<?php

namespace tests\unit\controllers\api;

use app\controllers\api\ScratchPadController;
use app\models\Project;
use app\models\ScratchPad;
use app\modules\identity\models\User;
use Codeception\Test\Unit;
use Yii;

class ScratchPadControllerTest extends Unit
{
    protected function _before(): void
    {
        // Clean up test data
        ScratchPad::deleteAll(['user_id' => 999]);
        Project::deleteAll(['user_id' => 999, 'name' => ['Auto Created Project', 'Existing Test Project']]);
    }

    protected function _after(): void
    {
        // Clean up test data
        ScratchPad::deleteAll(['user_id' => 999]);
        Project::deleteAll(['user_id' => 999]);
    }

    public function testCreateWithTextFormatConvertsToQuillDelta(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Test ScratchPad',
            'content' => 'Hello World',
            'format' => 'text',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertArrayHasKey('id', $result);
        self::assertSame(201, Yii::$app->response->statusCode);

        // Verify the scratch pad was created with delta format
        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertNotNull($scratchPad);
        self::assertSame('Test ScratchPad', $scratchPad->name);
        self::assertStringContainsString('"ops":', $scratchPad->content);
        self::assertStringContainsString('Hello World', $scratchPad->content);
    }

    public function testCreateWithDeltaObjectFormat(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Delta Test',
            'content' => ['ops' => [['insert' => "Bold text\n", 'attributes' => ['bold' => true]]]],
            'format' => 'delta',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertSame(201, Yii::$app->response->statusCode);

        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertNotNull($scratchPad);
        self::assertStringContainsString('bold', $scratchPad->content);
    }

    public function testCreateWithInvalidDeltaReturns400(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Invalid Delta',
            'content' => 'not valid json',
            'format' => 'delta',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertFalse($result['success']);
        self::assertArrayHasKey('errors', $result);
        self::assertArrayHasKey('content', $result['errors']);
        self::assertSame(400, Yii::$app->response->statusCode);
    }

    public function testCreateWithInvalidFormatReturns400(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Invalid Format',
            'content' => 'Test',
            'format' => 'invalid',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertFalse($result['success']);
        self::assertArrayHasKey('errors', $result);
        self::assertArrayHasKey('format', $result['errors']);
        self::assertSame(400, Yii::$app->response->statusCode);
    }

    public function testCreateWithMissingNameReturns422(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'content' => 'Test content',
            'format' => 'text',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertFalse($result['success']);
        self::assertArrayHasKey('errors', $result);
        self::assertArrayHasKey('name', $result['errors']);
        self::assertSame(422, Yii::$app->response->statusCode);
    }

    public function testCreateAutoCreatesProject(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Note with Project',
            'content' => 'Test',
            'format' => 'text',
            'project_name' => 'Auto Created Project',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        // Verify project was created
        $project = Project::find()->forUser(999)->withName('Auto Created Project')->one();
        self::assertNotNull($project);

        // Verify scratch pad is linked to project
        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertSame($project->id, $scratchPad->project_id);
    }

    public function testCreateWithExistingProjectUsesIt(): void
    {
        $this->mockAuthenticatedUser();

        // Create existing project
        $existingProject = new Project([
            'user_id' => 999,
            'name' => 'Existing Test Project',
        ]);
        $existingProject->save();

        $this->mockPostData([
            'name' => 'Note for Existing Project',
            'content' => 'Test',
            'format' => 'text',
            'project_name' => 'Existing Test Project',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        // Verify scratch pad is linked to existing project
        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertSame($existingProject->id, $scratchPad->project_id);
    }

    public function testCreateWithoutProjectNameCreatesUnlinkedScratchPad(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Unlinked Note',
            'content' => 'Test',
            'format' => 'text',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertNull($scratchPad->project_id);
    }

    public function testCreateWithMarkdownFormatConvertsToQuillDelta(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Markdown Note',
            'content' => "# Hello\n\nThis is **bold** and *italic*.",
            'format' => 'md',
        ]);

        $controller = new ScratchPadController('scratch-pad', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertArrayHasKey('id', $result);
        self::assertSame(201, Yii::$app->response->statusCode);

        // Verify the scratch pad was created with delta format containing proper formatting
        $scratchPad = ScratchPad::findOne($result['id']);
        self::assertNotNull($scratchPad);
        self::assertSame('Markdown Note', $scratchPad->name);
        self::assertStringContainsString('"ops":', $scratchPad->content);
        self::assertStringContainsString('Hello', $scratchPad->content);
        self::assertStringContainsString('"header":1', $scratchPad->content);
        self::assertStringContainsString('"bold":true', $scratchPad->content);
        self::assertStringContainsString('"italic":true', $scratchPad->content);
    }

    private function mockAuthenticatedUser(): void
    {
        // Create test user with ID 999 if doesn't exist
        $user = User::findOne(999);
        if (!$user) {
            Yii::$app->db->createCommand()->insert('user', [
                'id' => 999,
                'username' => 'apitest',
                'email' => 'apitest@example.com',
                'password_hash' => Yii::$app->security->generatePasswordHash('secret'),
                'auth_key' => Yii::$app->security->generateRandomString(),
                'status' => User::STATUS_ACTIVE,
                'created_at' => time(),
                'updated_at' => time(),
            ])->execute();
            $user = User::findOne(999);
        }

        Yii::$app->user->setIdentity($user);
    }

    private function mockPostData(array $data): void
    {
        Yii::$app->request->setBodyParams($data);
    }
}
