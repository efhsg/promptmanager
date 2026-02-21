<?php

namespace tests\unit\models;

use app\models\Project;
use app\models\ProjectWorktree;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ProjectWorktreeTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    private function createValidWorktree(array $overrides = []): ProjectWorktree
    {
        $model = new ProjectWorktree();
        $model->project_id = $overrides['project_id'] ?? 1;
        $model->purpose = $overrides['purpose'] ?? 'feature';
        $model->branch = $overrides['branch'] ?? 'feature/my-branch';
        $model->path_suffix = $overrides['path_suffix'] ?? 'my-feature';
        $model->source_branch = $overrides['source_branch'] ?? 'main';

        return $model;
    }

    public function testGetFullPathConcatenatesCorrectly(): void
    {
        $project = new Project();
        $project->root_directory = '/projects/app';

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'skills';
        $worktree->populateRelation('project', $project);

        verify($worktree->getFullPath())->equals('/projects/app-skills');
    }

    public function testGetFullPathTrimsTrailingSlash(): void
    {
        $project = new Project();
        $project->root_directory = '/projects/app/';

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'skills';
        $worktree->populateRelation('project', $project);

        verify($worktree->getFullPath())->equals('/projects/app-skills');
    }

    public function testGetFullPathReturnsNullWithoutRootDir(): void
    {
        $project = new Project();
        $project->root_directory = null;

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'skills';
        $worktree->populateRelation('project', $project);

        verify($worktree->getFullPath())->null();
    }

    public function testValidModelPassesValidation(): void
    {
        $model = $this->createValidWorktree();

        verify($model->validate())->true();
        verify($model->errors)->empty();
    }

    public function testPurposeAcceptsValidEnumValues(): void
    {
        foreach (['feature', 'bugfix', 'refactor', 'spike', 'custom'] as $purpose) {
            $model = $this->createValidWorktree([
                'purpose' => $purpose,
                'path_suffix' => 'test-' . $purpose,
            ]);
            verify($model->validate(['purpose']))->true();
        }
    }

    public function testPurposeRejectsInvalidValue(): void
    {
        $model = $this->createValidWorktree(['purpose' => 'invalid']);

        verify($model->validate(['purpose']))->false();
        verify($model->getErrors('purpose'))->notEmpty();
    }

    public function testBranchRejectsPathTraversal(): void
    {
        $model = $this->createValidWorktree(['branch' => '../../etc']);

        verify($model->validate(['branch']))->false();
        verify($model->getErrors('branch'))->notEmpty();
    }

    public function testSourceBranchRejectsPathTraversal(): void
    {
        $model = $this->createValidWorktree(['source_branch' => '../hack']);

        verify($model->validate(['source_branch']))->false();
        verify($model->getErrors('source_branch'))->notEmpty();
    }

    public function testSuffixRejectsSpecialChars(): void
    {
        $model = $this->createValidWorktree(['path_suffix' => 'my worktree!']);

        verify($model->validate(['path_suffix']))->false();
        verify($model->getErrors('path_suffix'))->notEmpty();
    }

    public function testSuffixRejectsSlashes(): void
    {
        $model = $this->createValidWorktree(['path_suffix' => 'my/feature']);

        verify($model->validate(['path_suffix']))->false();
        verify($model->getErrors('path_suffix'))->notEmpty();
    }

    public function testBranchAcceptsValidSlashes(): void
    {
        $model = $this->createValidWorktree(['branch' => 'feature/auth-flow']);

        verify($model->validate(['branch']))->true();
        verify($model->getErrors('branch'))->empty();
    }

    public function testBranchAcceptsDotsInName(): void
    {
        $model = $this->createValidWorktree(['branch' => 'release/v1.2.3']);

        verify($model->validate(['branch']))->true();
        verify($model->getErrors('branch'))->empty();
    }

    public function testRequiredFields(): void
    {
        $model = new ProjectWorktree();

        verify($model->validate())->false();
        verify($model->getErrors('project_id'))->notEmpty();
        verify($model->getErrors('purpose'))->notEmpty();
        verify($model->getErrors('branch'))->notEmpty();
        verify($model->getErrors('path_suffix'))->notEmpty();
    }

    public function testSourceBranchDefaultsToMain(): void
    {
        $model = $this->createValidWorktree(['source_branch' => null]);

        verify($model->validate())->true();
        verify($model->source_branch)->equals('main');
    }

    public function testTimestampsSetOnCreate(): void
    {
        $model = $this->createValidWorktree();
        $model->save(false);

        verify($model->created_at)->notNull();
        verify($model->updated_at)->notNull();

        // Clean up
        $model->delete();
    }

    public function testUniqueConstraintOnProjectSuffix(): void
    {
        $first = $this->createValidWorktree(['path_suffix' => 'duplicate-test']);
        $first->save(false);

        $second = $this->createValidWorktree(['path_suffix' => 'duplicate-test']);
        verify($second->validate())->false();
        verify($second->getErrors('path_suffix'))->notEmpty();

        // Clean up
        $first->delete();
    }

    public function testGetPurposeEnumReturnsCorrectEnum(): void
    {
        $model = $this->createValidWorktree(['purpose' => 'bugfix']);

        verify($model->getPurposeEnum()->value)->equals('bugfix');
        verify($model->getPurposeEnum()->label())->equals('Bugfix');
    }

    /**
     * @dataProvider invalidBranchProvider
     */
    public function testBranchRejectsInvalidCharacters(string $branch): void
    {
        $model = $this->createValidWorktree(['branch' => $branch]);

        verify($model->validate(['branch']))->false();
    }

    public static function invalidBranchProvider(): array
    {
        return [
            'spaces' => ['my branch'],
            'special chars' => ['branch@name'],
            'backtick' => ['branch`name'],
            'semicolon' => ['branch;name'],
            'ampersand' => ['branch&name'],
            'pipe' => ['branch|name'],
        ];
    }
}
