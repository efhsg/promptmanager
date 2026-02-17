<?php

namespace app\services;

use app\models\Note;
use app\models\Project;
use common\enums\LogCategory;
use common\enums\NoteType;
use RuntimeException;
use Throwable;
use Yii;

class NoteService
{
    /**
     * @throws RuntimeException when validation fails
     */
    public function saveNote(array $data, int $userId): Note
    {
        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $content = $data['content'] ?? '';
        $projectId = $data['project_id'] ?? null;
        $type = $data['type'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        // Validate type
        if ($type !== null) {
            $noteType = NoteType::tryFrom($type);
            if ($noteType === null) {
                throw new RuntimeException('Invalid note type.');
            }
        }

        // Validate parent_id ownership and cache for project inheritance
        $parent = null;
        if ($parentId !== null) {
            $parent = Note::find()
                ->forUser($userId)
                ->andWhere(['id' => $parentId])
                ->one();
            if ($parent === null) {
                throw new RuntimeException('Parent note not found.');
            }
        }

        // Validate project ownership
        if ($projectId !== null) {
            $projectExists = Project::find()
                ->forUser($userId)
                ->andWhere(['id' => $projectId])
                ->exists();
            if (!$projectExists) {
                throw new RuntimeException('Project not found.');
            }
        }

        // Inherit project_id from parent on create
        if ($id === null && $parent !== null && $projectId === null) {
            $projectId = $parent->project_id;
        }

        if ($id !== null) {
            $model = Note::find()
                ->forUser($userId)
                ->andWhere(['id' => $id])
                ->one();
            if ($model === null) {
                throw new RuntimeException('Note not found.');
            }
        } else {
            $model = new Note([
                'user_id' => $userId,
            ]);
        }

        $model->name = $name;
        $model->content = $content;
        $model->project_id = $projectId;

        if ($type !== null) {
            $model->type = $type;
        }
        if ($parentId !== null && $model->isNewRecord) {
            $model->parent_id = $parentId;
        }

        if (!$model->save()) {
            $errors = $model->getFirstErrors();
            throw new RuntimeException(reset($errors) ?: 'Failed to save note.');
        }

        return $model;
    }

    public function deleteNote(Note $model): bool
    {
        try {
            return (bool) $model->delete();
        } catch (Throwable $e) {
            Yii::error($e->getMessage(), LogCategory::DATABASE->value);
            return false;
        }
    }

    /**
     * Merges parent + children content into single Quill Delta JSON.
     */
    public function fetchMergedContent(Note $note): string
    {
        $children = $note->getChildren()->orderBy(['created_at' => SORT_ASC])->all();

        if (empty($children)) {
            return $note->content ?? '{"ops":[{"insert":"\\n"}]}';
        }

        $parentOps = $this->decodeOps($note->content);
        // Remove trailing newline from parent
        $parentOps = $this->removeTrailingNewline($parentOps);

        $allOps = $parentOps;
        $lastIndex = count($children) - 1;

        foreach ($children as $index => $child) {
            // Add separator
            $allOps[] = ['insert' => "\n\n"];

            $childOps = $this->decodeOps($child->content);

            // Remove trailing newline for all children except last
            if ($index !== $lastIndex) {
                $childOps = $this->removeTrailingNewline($childOps);
            }

            $allOps = array_merge($allOps, $childOps);
        }

        return json_encode(['ops' => $allOps]);
    }

    private function decodeOps(?string $content): array
    {
        if ($content === null || $content === '') {
            return [['insert' => "\n"]];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['ops'])) {
            return [['insert' => "\n"]];
        }

        return $decoded['ops'];
    }

    private function removeTrailingNewline(array $ops): array
    {
        if (empty($ops)) {
            return $ops;
        }

        $lastIndex = count($ops) - 1;
        $lastOp = $ops[$lastIndex];

        if (isset($lastOp['insert']) && $lastOp['insert'] === "\n" && count($lastOp) === 1) {
            array_pop($ops);
        }

        return $ops;
    }
}
