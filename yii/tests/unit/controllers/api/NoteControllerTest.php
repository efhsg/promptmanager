<?php

namespace tests\unit\controllers\api;

use app\controllers\api\NoteController;
use app\models\Note;
use app\models\Project;
use app\modules\identity\models\User;
use Codeception\Test\Unit;
use Yii;

class NoteControllerTest extends Unit
{
    protected function _before(): void
    {
        Note::deleteAll(['user_id' => 999]);
        Project::deleteAll(['user_id' => 999, 'name' => ['Auto Created Project', 'Existing Test Project']]);
    }

    protected function _after(): void
    {
        Note::deleteAll(['user_id' => 999]);
        Project::deleteAll(['user_id' => 999]);
    }

    public function testCreateWithTextFormatConvertsToQuillDelta(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Test Note',
            'content' => 'Hello World',
            'format' => 'text',
        ]);

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertArrayHasKey('id', $result);
        self::assertSame(201, Yii::$app->response->statusCode);

        $note = Note::findOne($result['id']);
        self::assertNotNull($note);
        self::assertSame('Test Note', $note->name);
        self::assertStringContainsString('"ops":', $note->content);
        self::assertStringContainsString('Hello World', $note->content);
    }

    public function testCreateWithDeltaObjectFormat(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Delta Test',
            'content' => ['ops' => [['insert' => "Bold text\n", 'attributes' => ['bold' => true]]]],
            'format' => 'delta',
        ]);

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertSame(201, Yii::$app->response->statusCode);

        $note = Note::findOne($result['id']);
        self::assertNotNull($note);
        self::assertStringContainsString('bold', $note->content);
    }

    public function testCreateWithInvalidDeltaReturns400(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Invalid Delta',
            'content' => 'not valid json',
            'format' => 'delta',
        ]);

        $controller = new NoteController('note', Yii::$app);

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

        $controller = new NoteController('note', Yii::$app);

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

        $controller = new NoteController('note', Yii::$app);

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

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        $project = Project::find()->forUser(999)->withName('Auto Created Project')->one();
        self::assertNotNull($project);

        $note = Note::findOne($result['id']);
        self::assertSame($project->id, $note->project_id);
    }

    public function testCreateWithExistingProjectUsesIt(): void
    {
        $this->mockAuthenticatedUser();

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

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        $note = Note::findOne($result['id']);
        self::assertSame($existingProject->id, $note->project_id);
    }

    public function testCreateWithoutProjectNameCreatesUnlinkedNote(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Unlinked Note',
            'content' => 'Test',
            'format' => 'text',
        ]);

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);

        $note = Note::findOne($result['id']);
        self::assertNull($note->project_id);
    }

    public function testCreateWithMarkdownFormatConvertsToQuillDelta(): void
    {
        $this->mockAuthenticatedUser();
        $this->mockPostData([
            'name' => 'Markdown Note',
            'content' => "# Hello\n\nThis is **bold** and *italic*.",
            'format' => 'md',
        ]);

        $controller = new NoteController('note', Yii::$app);

        $result = $controller->actionCreate();

        self::assertTrue($result['success']);
        self::assertArrayHasKey('id', $result);
        self::assertSame(201, Yii::$app->response->statusCode);

        $note = Note::findOne($result['id']);
        self::assertNotNull($note);
        self::assertSame('Markdown Note', $note->name);
        self::assertStringContainsString('"ops":', $note->content);
        self::assertStringContainsString('Hello', $note->content);
        self::assertStringContainsString('"header":1', $note->content);
        self::assertStringContainsString('"bold":true', $note->content);
        self::assertStringContainsString('"italic":true', $note->content);
    }

    private function mockAuthenticatedUser(): void
    {
        $user = User::findOne(999);
        if (!$user) {
            Yii::$app->db->createCommand()->insert('user', [
                'id' => 999,
                'username' => 'apitest',
                'email' => 'apitest@example.com',
                'password_hash' => Yii::$app->security->generatePasswordHash('secret'),
                'auth_key' => Yii::$app->security->generateRandomString(),
                'status' => User::STATUS_ACTIVE,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
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
