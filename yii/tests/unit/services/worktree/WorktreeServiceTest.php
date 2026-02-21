<?php

namespace tests\unit\services\worktree;

use app\models\Project;
use app\models\ProjectWorktree;
use app\services\PathService;
use app\services\worktree\WorktreeService;
use Codeception\Test\Unit;
use common\enums\WorktreePurpose;
use RuntimeException;
use tests\fixtures\ProjectFixture;
use tests\fixtures\ProjectWorktreeFixture;
use tests\fixtures\UserFixture;

class WorktreeServiceTest extends Unit
{
    private WorktreeService $service;
    private string $tempDir;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'worktrees' => ProjectWorktreeFixture::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorktreeService(new PathService());
        $this->tempDir = sys_get_temp_dir() . '/worktree-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        // Clean up temp directories
        if (is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
        // Clean up any sibling worktree dirs
        foreach (glob($this->tempDir . '-*') as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
        parent::tearDown();
    }

    // --- Path calculation tests ---

    public function testGetWorktreePathReturnsNullWithoutRootDir(): void
    {
        $project = new Project();
        $project->root_directory = null;

        verify($this->service->getWorktreePath($project, 'skills'))->null();
    }

    public function testGetWorktreePathReturnsSuffixedPath(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $project = new Project();
        $project->root_directory = $this->tempDir;

        $result = $this->service->getWorktreePath($project, 'skills');

        verify($result)->equals($this->tempDir . '-skills');
    }

    public function testGetWorktreePathTranslatesHostPath(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $pathService = new PathService([$this->tempDir => '/container/project']);
        $service = new WorktreeService($pathService);

        $project = new Project();
        $project->root_directory = $this->tempDir;

        $result = $service->getWorktreePath($project, 'skills');

        verify($result)->equals('/container/project-skills');
    }

    public function testGetContainerPathTranslatesViaPathService(): void
    {
        $pathService = new PathService(['/host/project' => '/container/project']);
        $service = new WorktreeService($pathService);

        $project = new Project();
        $project->root_directory = '/host/project';

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'skills';
        $worktree->populateRelation('project', $project);

        $result = $service->getContainerPath($worktree);

        verify($result)->equals('/container/project-skills');
    }

    // --- Git repo detection ---

    public function testIsGitRepoReturnsFalseForNonRepo(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $project = new Project();
        $project->root_directory = $this->tempDir;

        verify($this->service->isGitRepo($project))->false();
    }

    public function testIsGitRepoReturnsTrueForRepo(): void
    {
        mkdir($this->tempDir, 0o777, true);
        exec('git -C ' . escapeshellarg($this->tempDir) . ' init 2>&1');

        $project = new Project();
        $project->root_directory = $this->tempDir;

        verify($this->service->isGitRepo($project))->true();
    }

    public function testIsGitRepoReturnsFalseWhenNoRootDir(): void
    {
        $project = new Project();
        $project->root_directory = null;

        verify($this->service->isGitRepo($project))->false();
    }

    // --- Create ---

    public function testCreateRejectsNonGitRepo(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $project = new Project();
        $project->root_directory = $this->tempDir;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Root directory is not a git repository.');

        $this->service->create(
            $project,
            'feature/test',
            'test',
            WorktreePurpose::Feature
        );
    }

    public function testCreateRejectsProjectWithoutRootDir(): void
    {
        $project = new Project();
        $project->root_directory = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Project has no root directory configured.');

        $this->service->create(
            $project,
            'feature/test',
            'test',
            WorktreePurpose::Feature
        );
    }

    public function testCreateRejectsInvalidBranchBeforeGitCommands(): void
    {
        $this->initGitRepo();
        $project = $this->createProjectWithRootDir();

        $this->expectException(RuntimeException::class);

        $this->service->create(
            $project,
            'invalid branch!',
            'test',
            WorktreePurpose::Feature
        );

        // Verify no worktree directory was created (validation stopped before git)
        verify(is_dir($this->tempDir . '-test'))->false();
    }

    public function testCreateStoresDbRecord(): void
    {
        $this->initGitRepo();
        $project = $this->createProjectWithRootDir();

        $worktree = $this->service->create(
            $project,
            'feature/unit-test',
            'unit-test',
            WorktreePurpose::Feature
        );

        verify($worktree->id)->notNull();
        verify($worktree->project_id)->equals($project->id);
        verify($worktree->branch)->equals('feature/unit-test');
        verify($worktree->path_suffix)->equals('unit-test');
        verify($worktree->purpose)->equals('feature');
        verify($worktree->source_branch)->equals('main');

        // Verify worktree directory was created
        verify(is_dir($this->tempDir . '-unit-test'))->true();

        // Clean up
        $this->service->remove($worktree);
    }

    // --- Sync ---

    public function testSyncFailsWhenDirectoryMissing(): void
    {
        $project = new Project();
        $project->root_directory = '/nonexistent/path';

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'missing';
        $worktree->source_branch = 'main';
        $worktree->populateRelation('project', $project);

        $result = $this->service->sync($worktree);

        verify($result->success)->false();
        verify($result->errorMessage)->equals('Worktree directory does not exist.');
    }

    public function testSyncReturnsSuccessWhenUpToDate(): void
    {
        $this->initGitRepo();
        $project = $this->createProjectWithRootDir();

        $worktree = $this->service->create($project, 'feature/sync-test', 'sync-test', WorktreePurpose::Feature);
        $worktree->populateRelation('project', $project);

        $result = $this->service->sync($worktree);

        verify($result->success)->true();
        verify($result->commitsMerged)->equals(0);
        verify($result->errorMessage)->null();

        $this->service->remove($worktree);
    }

    // --- Remove ---

    public function testRemoveDeletesDbRecordAndDirectory(): void
    {
        $this->initGitRepo();
        $project = $this->createProjectWithRootDir();

        $worktree = $this->service->create($project, 'feature/remove-test', 'remove-test', WorktreePurpose::Feature);
        $worktree->populateRelation('project', $project);
        $id = $worktree->id;

        verify(ProjectWorktree::findOne($id))->notNull();
        verify(is_dir($this->tempDir . '-remove-test'))->true();

        $this->service->remove($worktree);
        clearstatcache();

        verify(ProjectWorktree::findOne($id))->null();
        verify(is_dir($this->tempDir . '-remove-test'))->false();
    }

    // --- Status ---

    public function testGetStatusDetectsMissingDirectory(): void
    {
        $project = new Project();
        $project->root_directory = '/nonexistent/path';

        $worktree = new ProjectWorktree();
        $worktree->id = 999;
        $worktree->path_suffix = 'gone';
        $worktree->branch = 'feature/gone';
        $worktree->source_branch = 'main';
        $worktree->purpose = 'feature';
        $worktree->populateRelation('project', $project);

        $status = $this->service->getStatus($worktree);

        verify($status->directoryExists)->false();
        verify($status->worktreeId)->equals(999);
        verify($status->behindSourceCount)->equals(0);
    }

    public function testGetStatusForProjectReturnsEmptyWhenNoWorktrees(): void
    {
        // Project 4 (user 100) has no worktrees in fixtures
        $project = Project::findOne(4);

        $statuses = $this->service->getStatusForProject($project);

        verify($statuses)->equals([]);
    }

    // --- Cleanup ---

    public function testCleanupRefusesWhenDirectoryExists(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $suffixDir = $this->tempDir . '-cleanup';
        mkdir($suffixDir, 0o777, true);

        $project = new Project();
        $project->root_directory = $this->tempDir;

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'cleanup';
        $worktree->populateRelation('project', $project);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot clean up: worktree directory still exists.');

        $this->service->cleanup($worktree);

        // Clean up extra dir
        rmdir($suffixDir);
    }

    public function testCleanupRemovesOrphanedRecord(): void
    {
        // Create a worktree record pointing to a non-existent directory
        $worktree = new ProjectWorktree();
        $worktree->project_id = 1;
        $worktree->purpose = 'feature';
        $worktree->branch = 'feature/orphan';
        $worktree->path_suffix = 'orphan-cleanup-test';
        $worktree->source_branch = 'main';
        $worktree->save(false);

        $id = $worktree->id;
        verify(ProjectWorktree::findOne($id))->notNull();

        $this->service->cleanup($worktree);

        verify(ProjectWorktree::findOne($id))->null();
    }

    // --- Recreate ---

    public function testRecreateFailsWhenDirectoryExists(): void
    {
        mkdir($this->tempDir, 0o777, true);
        $suffixDir = $this->tempDir . '-existing';
        mkdir($suffixDir, 0o777, true);

        $project = new Project();
        $project->root_directory = $this->tempDir;

        $worktree = new ProjectWorktree();
        $worktree->path_suffix = 'existing';
        $worktree->branch = 'feature/test';
        $worktree->source_branch = 'main';
        $worktree->populateRelation('project', $project);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot re-create: worktree directory already exists.');

        $this->service->recreate($worktree);

        rmdir($suffixDir);
    }

    // --- Helpers ---

    private function initGitRepo(): void
    {
        mkdir($this->tempDir, 0o777, true);
        exec('git -C ' . escapeshellarg($this->tempDir) . ' init -b main 2>&1');
        exec('git -C ' . escapeshellarg($this->tempDir) . ' config user.email "test@test.com" 2>&1');
        exec('git -C ' . escapeshellarg($this->tempDir) . ' config user.name "Test" 2>&1');
        exec('git -C ' . escapeshellarg($this->tempDir) . ' commit --allow-empty -m "initial" 2>&1');
    }

    private function createProjectWithRootDir(): Project
    {
        $project = new Project();
        $project->id = 1;
        $project->user_id = 100;
        $project->name = 'Worktree Test Project';
        $project->root_directory = $this->tempDir;

        return $project;
    }
}
