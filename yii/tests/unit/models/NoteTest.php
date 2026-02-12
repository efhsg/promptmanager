<?php

namespace tests\unit\models;

use app\models\Note;
use common\enums\NoteType;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class NoteTest extends Unit
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
        $model = new Note();
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
            'valid with project' => ['Test Note', 100, 1, true, []],
            'valid global (null project)' => ['Global Note', 100, null, true, []],
            'missing name' => [null, 100, null, false, ['name']],
            'missing user_id' => ['Test Note', null, null, false, ['user_id']],
            'maximum length name' => [str_repeat('a', 255), 100, null, true, []],
            'exceeding length name' => [str_repeat('a', 256), 100, null, false, ['name']],
            'invalid user' => ['Test Note', 99999, null, false, ['user_id']],
            'invalid project' => ['Test Note', 100, 99999, false, ['project_id']],
        ];
    }

    public function testProjectIdCanBeNull(): void
    {
        $model = new Note();
        $model->name = 'Global Note';
        $model->user_id = 100;
        $model->project_id = null;

        verify($model->validate(['project_id']))->true();
        verify($model->getErrors('project_id'))->empty();
    }

    public function testContentCanBeNull(): void
    {
        $model = new Note();
        $model->name = 'Empty Content Note';
        $model->user_id = 100;
        $model->content = null;

        verify($model->validate(['content']))->true();
        verify($model->getErrors('content'))->empty();
    }

    public function testContentCanStoreQuillDelta(): void
    {
        $quillDelta = '{"ops":[{"insert":"Hello World\\n"}]}';

        $model = new Note();
        $model->name = 'Quill Content Note';
        $model->user_id = 100;
        $model->content = $quillDelta;

        verify($model->validate(['content']))->true();
        verify($model->content)->equals($quillDelta);
    }

    public function testTimestampsAreUpdatedOnSave(): void
    {
        try {
            Note::setTimestampOverride(date('Y-m-d H:i:s', 1_700_000_000));

            $model = new Note();
            $model->name = 'Timestamp Test Note';
            $model->user_id = 100;
            verify($model->save())->true();

            $originalCreatedAt = $model->created_at;
            $originalUpdatedAt = $model->updated_at;

            Note::setTimestampOverride(date('Y-m-d H:i:s', strtotime($originalUpdatedAt) + 10));
            $model->name = 'Updated Timestamp Test Note';
            verify($model->save())->true();

            verify($model->created_at)->equals($originalCreatedAt);
            verify($model->updated_at)->greaterThan($originalUpdatedAt);
        } finally {
            Note::setTimestampOverride(null);
        }
    }

    public function testUserRelation(): void
    {
        $model = new Note();
        $model->name = 'Relation Test Note';
        $model->user_id = 100;
        verify($model->save())->true();

        $user = $model->user;
        verify($user)->notEmpty();
        verify($user->id)->equals($model->user_id);
    }

    public function testProjectRelation(): void
    {
        $model = new Note();
        $model->name = 'Project Relation Test Note';
        $model->user_id = 100;
        $model->project_id = 1;
        verify($model->save())->true();

        $project = $model->project;
        verify($project)->notEmpty();
        verify($project->id)->equals($model->project_id);
    }

    public function testGlobalNoteHasNoProjectRelation(): void
    {
        $model = new Note();
        $model->name = 'Global Note No Project';
        $model->user_id = 100;
        $model->project_id = null;
        verify($model->save())->true();

        verify($model->project)->empty();
    }

    public function testContentCanStoreEmojiCharacters(): void
    {
        $contentWithEmoji = '{"ops":[{"insert":"🧠 Brain emoji test\\n"}]}';

        $model = new Note();
        $model->name = 'Emoji Content Test';
        $model->user_id = 100;
        $model->content = $contentWithEmoji;

        verify($model->save())->true();

        $loaded = Note::findOne($model->id);
        verify($loaded)->notEmpty();
        verify($loaded->content)->equals($contentWithEmoji);
    }

    public function testDefaultTypeIsNote(): void
    {
        $model = new Note();
        verify($model->type)->equals(NoteType::NOTE->value);
    }

    public function testTypeValidation(): void
    {
        $model = new Note();
        $model->name = 'Type Test';
        $model->user_id = 100;
        $model->type = 'invalid_type';

        verify($model->validate(['type']))->false();
        verify($model->getErrors('type'))->notEmpty();
    }

    public function testValidTypeValues(): void
    {
        foreach (NoteType::values() as $type) {
            $model = new Note();
            $model->name = "Type $type Test";
            $model->user_id = 100;
            $model->type = $type;

            verify($model->validate(['type']))->true();
        }
    }

    public function testResolveHandlesLegacyResponseType(): void
    {
        verify(NoteType::resolve('response'))->same(NoteType::SUMMATION);
        verify(NoteType::resolve('summation'))->same(NoteType::SUMMATION);
        verify(NoteType::resolve('note'))->same(NoteType::NOTE);
        verify(NoteType::resolve('import'))->same(NoteType::IMPORT);
        verify(NoteType::resolve('unknown'))->null();
        verify(NoteType::resolve(null))->null();
    }

    public function testParentRelation(): void
    {
        $parent = new Note();
        $parent->name = 'Parent Note';
        $parent->user_id = 100;
        verify($parent->save())->true();

        $child = new Note();
        $child->name = 'Child Note';
        $child->user_id = 100;
        $child->parent_id = $parent->id;
        $child->type = NoteType::SUMMATION->value;
        verify($child->save())->true();

        verify($child->parent)->notEmpty();
        verify($child->parent->id)->equals($parent->id);
    }

    public function testChildrenRelation(): void
    {
        $parent = new Note();
        $parent->name = 'Parent With Children';
        $parent->user_id = 100;
        verify($parent->save())->true();

        $child1 = new Note();
        $child1->name = 'Child 1';
        $child1->user_id = 100;
        $child1->parent_id = $parent->id;
        $child1->type = NoteType::SUMMATION->value;
        verify($child1->save())->true();

        $child2 = new Note();
        $child2->name = 'Child 2';
        $child2->user_id = 100;
        $child2->parent_id = $parent->id;
        $child2->type = NoteType::SUMMATION->value;
        verify($child2->save())->true();

        $parent->refresh();
        verify(count($parent->children))->equals(2);
    }

    public function testParentIdMustReferenceExistingNote(): void
    {
        $model = new Note();
        $model->name = 'Invalid Parent';
        $model->user_id = 100;
        $model->parent_id = 99999;

        verify($model->validate(['parent_id']))->false();
        verify($model->getErrors('parent_id'))->notEmpty();
    }
}
