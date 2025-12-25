<?php

namespace tests\unit\models;

use app\models\ScratchPad;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ScratchPadTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    /**
     * @dataProvider validationProvider
     */
    public function testValidation(?string $name, ?int $userId, ?int $projectId, bool $isValid, array $expectedErrorFields): void
    {
        $model = new ScratchPad();
        $model->name = $name;
        $model->user_id = $userId;
        $model->project_id = $projectId;

        $result = $model->validate();
        verify($result)->equals($isValid);

        if ($isValid) {
            verify($model->errors)->empty();
            return;
        }

        verify($model->errors)->notEmpty();
        foreach ($expectedErrorFields as $field) {
            verify(array_key_exists($field, $model->errors))->true();
        }
    }

    public static function validationProvider(): array
    {
        return [
            'valid with project' => ['Test Pad', 100, 1, true, []],
            'valid global (null project)' => ['Global Pad', 100, null, true, []],
            'missing name' => [null, 100, null, false, ['name']],
            'missing user_id' => ['Test Pad', null, null, false, ['user_id']],
            'maximum length name' => [str_repeat('a', 255), 100, null, true, []],
            'exceeding length name' => [str_repeat('a', 256), 100, null, false, ['name']],
            'invalid user' => ['Test Pad', 99999, null, false, ['user_id']],
            'invalid project' => ['Test Pad', 100, 99999, false, ['project_id']],
        ];
    }

    public function testProjectIdCanBeNull(): void
    {
        $model = new ScratchPad();
        $model->name = 'Global Scratch Pad';
        $model->user_id = 100;
        $model->project_id = null;

        verify($model->validate(['project_id']))->true();
        verify($model->getErrors('project_id'))->empty();
    }

    public function testContentCanBeNull(): void
    {
        $model = new ScratchPad();
        $model->name = 'Empty Content Pad';
        $model->user_id = 100;
        $model->content = null;

        verify($model->validate(['content']))->true();
        verify($model->getErrors('content'))->empty();
    }

    public function testContentCanStoreQuillDelta(): void
    {
        $quillDelta = '{"ops":[{"insert":"Hello World\\n"}]}';

        $model = new ScratchPad();
        $model->name = 'Quill Content Pad';
        $model->user_id = 100;
        $model->content = $quillDelta;

        verify($model->validate(['content']))->true();
        verify($model->content)->equals($quillDelta);
    }

    public function testTimestampsAreUpdatedOnSave(): void
    {
        try {
            ScratchPad::setTimestampOverride(1_700_000_000);

            $model = new ScratchPad();
            $model->name = 'Timestamp Test Pad';
            $model->user_id = 100;
            verify($model->save())->true();

            $originalCreatedAt = $model->created_at;
            $originalUpdatedAt = $model->updated_at;

            ScratchPad::setTimestampOverride($originalUpdatedAt + 10);
            $model->name = 'Updated Timestamp Test Pad';
            verify($model->save())->true();

            verify($model->created_at)->equals($originalCreatedAt);
            verify($model->updated_at)->greaterThan($originalUpdatedAt);
        } finally {
            ScratchPad::setTimestampOverride(null);
        }
    }

    public function testUserRelation(): void
    {
        $model = new ScratchPad();
        $model->name = 'Relation Test Pad';
        $model->user_id = 100;
        verify($model->save())->true();

        $user = $model->user;
        verify($user)->notEmpty();
        verify($user->id)->equals($model->user_id);
    }

    public function testProjectRelation(): void
    {
        $model = new ScratchPad();
        $model->name = 'Project Relation Test Pad';
        $model->user_id = 100;
        $model->project_id = 1;
        verify($model->save())->true();

        $project = $model->project;
        verify($project)->notEmpty();
        verify($project->id)->equals($model->project_id);
    }

    public function testGlobalPadHasNoProjectRelation(): void
    {
        $model = new ScratchPad();
        $model->name = 'Global Pad No Project';
        $model->user_id = 100;
        $model->project_id = null;
        verify($model->save())->true();

        verify($model->project)->empty();
    }
}
