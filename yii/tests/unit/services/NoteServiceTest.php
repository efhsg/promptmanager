<?php

namespace tests\unit\services;

use app\models\Note;
use app\services\NoteService;
use Codeception\Test\Unit;
use common\enums\NoteType;
use RuntimeException;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class NoteServiceTest extends Unit
{
    private NoteService $service;

    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new NoteService();
    }

    public function testSaveNoteCreatesNewNote(): void
    {
        $note = $this->service->saveNote([
            'name' => 'Test Note',
            'content' => '{"ops":[{"insert":"Hello\\n"}]}',
            'project_id' => 1,
        ], 100);

        $this->assertNotNull($note->id);
        $this->assertSame('Test Note', $note->name);
        $this->assertSame(100, $note->user_id);
        $this->assertSame(1, $note->project_id);
    }

    public function testSaveNoteUpdatesExistingNote(): void
    {
        $note = $this->service->saveNote([
            'name' => 'Original Name',
            'content' => '{"ops":[{"insert":"Original\\n"}]}',
        ], 100);

        $updated = $this->service->saveNote([
            'id' => $note->id,
            'name' => 'Updated Name',
            'content' => '{"ops":[{"insert":"Updated\\n"}]}',
        ], 100);

        $this->assertSame($note->id, $updated->id);
        $this->assertSame('Updated Name', $updated->name);
    }

    public function testSaveNoteThrowsWhenNameEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Name is required.');

        $this->service->saveNote(['name' => '', 'content' => 'test'], 100);
    }

    public function testSaveNoteThrowsForInvalidType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid note type.');

        $this->service->saveNote([
            'name' => 'Test',
            'content' => 'test',
            'type' => 'invalid_type',
        ], 100);
    }

    public function testSaveNoteThrowsForInvalidParent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parent note not found.');

        $this->service->saveNote([
            'name' => 'Child',
            'content' => 'test',
            'parent_id' => 99999,
        ], 100);
    }

    public function testSaveNoteInheritsProjectFromParent(): void
    {
        $parent = $this->service->saveNote([
            'name' => 'Parent',
            'content' => '{"ops":[{"insert":"Parent\\n"}]}',
            'project_id' => 1,
        ], 100);

        $child = $this->service->saveNote([
            'name' => 'Child',
            'content' => '{"ops":[{"insert":"Child\\n"}]}',
            'parent_id' => $parent->id,
            'type' => NoteType::SUMMATION->value,
        ], 100);

        $this->assertSame($parent->project_id, $child->project_id);
    }

    public function testDeleteNote(): void
    {
        $note = $this->service->saveNote([
            'name' => 'To Delete',
            'content' => 'test',
        ], 100);

        $id = $note->id;
        $this->assertTrue($this->service->deleteNote($note));
        $this->assertNull(Note::findOne($id));
    }

    public function testFetchMergedContentWithoutChildren(): void
    {
        $note = $this->service->saveNote([
            'name' => 'Solo Note',
            'content' => '{"ops":[{"insert":"Hello World\\n"}]}',
        ], 100);

        $merged = $this->service->fetchMergedContent($note);

        $this->assertSame('{"ops":[{"insert":"Hello World\\n"}]}', $merged);
    }

    public function testFetchMergedContentWithChildren(): void
    {
        $parent = $this->service->saveNote([
            'name' => 'Parent',
            'content' => '{"ops":[{"insert":"Parent content"},{"insert":"\\n"}]}',
        ], 100);

        $this->service->saveNote([
            'name' => 'Child 1',
            'content' => '{"ops":[{"insert":"Child 1 content"},{"insert":"\\n"}]}',
            'parent_id' => $parent->id,
            'type' => NoteType::SUMMATION->value,
        ], 100);

        $this->service->saveNote([
            'name' => 'Child 2',
            'content' => '{"ops":[{"insert":"Child 2 content"},{"insert":"\\n"}]}',
            'parent_id' => $parent->id,
            'type' => NoteType::SUMMATION->value,
        ], 100);

        $parent->refresh();
        $merged = $this->service->fetchMergedContent($parent);
        $decoded = json_decode($merged, true);

        $this->assertArrayHasKey('ops', $decoded);

        // Extract all insert texts
        $texts = array_map(fn($op) => $op['insert'] ?? '', $decoded['ops']);
        $fullText = implode('', $texts);

        $this->assertStringContainsString('Parent content', $fullText);
        $this->assertStringContainsString('Child 1 content', $fullText);
        $this->assertStringContainsString('Child 2 content', $fullText);
    }

    public function testFetchMergedContentWithEmptyContent(): void
    {
        $note = $this->service->saveNote([
            'name' => 'Empty Note',
            'content' => '',
        ], 100);

        $merged = $this->service->fetchMergedContent($note);

        // Empty string content returns as-is when there are no children
        $this->assertSame('', $merged);
    }
}
