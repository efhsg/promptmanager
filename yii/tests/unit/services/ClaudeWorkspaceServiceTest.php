<?php

namespace tests\unit\services;

use app\models\Project;
use app\services\ClaudeWorkspaceService;
use Codeception\Test\Unit;
use Yii;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ClaudeWorkspaceServiceTest extends Unit
{
    private ClaudeWorkspaceService $service;
    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClaudeWorkspaceService();
        $this->testBasePath = Yii::getAlias('@app/storage/projects');
    }

    protected function tearDown(): void
    {
        // Clean up test workspaces
        $this->cleanupTestWorkspaces();
        parent::tearDown();
    }

    private function cleanupTestWorkspaces(): void
    {
        // Clean up any test-created directories
        $testDirs = ['999999', '999998', 'default'];
        foreach ($testDirs as $dir) {
            $path = $this->testBasePath . '/' . $dir;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    public function testGetWorkspacePathReturnsCorrectPath(): void
    {
        $project = new Project(['id' => 999999, 'name' => 'Test', 'user_id' => 1]);

        $path = $this->service->getWorkspacePath($project);

        $this->assertStringEndsWith('/storage/projects/999999', $path);
    }

    public function testEnsureWorkspaceCreatesDirectory(): void
    {
        $project = new Project(['id' => 999999, 'name' => 'Test', 'user_id' => 1]);

        $path = $this->service->ensureWorkspace($project);

        $this->assertTrue(is_dir($path));
        $this->assertTrue(is_dir($path . '/.claude'));
    }

    public function testSyncConfigCreatesClaudeMdFile(): void
    {
        $project = new Project([
            'id' => 999999,
            'name' => 'Test Project',
            'user_id' => 1,
            'ai_context' => '## Guidelines\n\nFollow PSR-12.',
        ]);

        $this->service->syncConfig($project);
        $path = $this->service->getWorkspacePath($project);

        $this->assertFileExists($path . '/CLAUDE.md');
        $content = file_get_contents($path . '/CLAUDE.md');
        $this->assertStringContainsString('**Test Project**', $content);
        $this->assertStringContainsString('## Guidelines', $content);
    }

    public function testSyncConfigCreatesSettingsFile(): void
    {
        $project = new Project([
            'id' => 999999,
            'name' => 'Test Project',
            'user_id' => 1,
        ]);
        $project->setAiOptions(['permissionMode' => 'plan', 'model' => 'sonnet']);

        $this->service->syncConfig($project);
        $path = $this->service->getWorkspacePath($project);

        $this->assertFileExists($path . '/.claude/settings.local.json');
        $settings = json_decode(file_get_contents($path . '/.claude/settings.local.json'), true);
        $this->assertSame('plan', $settings['permissions']['defaultMode']);
        $this->assertSame('sonnet', $settings['model']);
    }

    public function testDeleteWorkspaceRemovesDirectory(): void
    {
        $project = new Project(['id' => 999999, 'name' => 'Test', 'user_id' => 1]);

        // First create the workspace
        $path = $this->service->ensureWorkspace($project);
        $this->assertTrue(is_dir($path));

        // Then delete it
        $this->service->deleteWorkspace($project);

        $this->assertFalse(is_dir($path));
    }

    public function testGenerateClaudeMdIncludesProjectName(): void
    {
        $project = new Project([
            'name' => 'My Test Project',
            'user_id' => 100,
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringContainsString('**My Test Project**', $result);
    }

    public function testGenerateClaudeMdIncludesClaudeContextWhenSet(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'ai_context' => '## Custom Instructions\n\nFollow PSR-12.',
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringContainsString('## Project Context', $result);
        $this->assertStringContainsString('## Custom Instructions', $result);
    }

    public function testGenerateClaudeMdExcludesContextSectionWhenEmpty(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'ai_context' => null,
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringNotContainsString('## Project Context', $result);
    }

    public function testGenerateClaudeMdIncludesFileExtensions(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'allowed_file_extensions' => 'php,js,vue',
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringContainsString('## File Patterns', $result);
        $this->assertStringContainsString('`.php`', $result);
        $this->assertStringContainsString('`.js`', $result);
        $this->assertStringContainsString('`.vue`', $result);
    }

    public function testGenerateClaudeMdIncludesBlacklistedDirectories(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'blacklisted_directories' => 'vendor,node_modules',
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringContainsString('## Excluded Directories', $result);
        $this->assertStringContainsString('`vendor/`', $result);
        $this->assertStringContainsString('`node_modules/`', $result);
    }

    public function testGenerateClaudeMdIncludesBlacklistExceptions(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'blacklisted_directories' => 'vendor/[mypackage]',
        ]);

        $result = $this->service->generateClaudeMd($project);

        $this->assertStringContainsString('`vendor/`', $result);
        $this->assertStringContainsString('except: mypackage', $result);
    }

    public function testGenerateSettingsJsonIncludesPermissionMode(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
        ]);
        $project->setAiOptions(['permissionMode' => 'plan']);

        $result = $this->service->generateSettingsJson($project);

        $this->assertArrayHasKey('permissions', $result);
        $this->assertSame('plan', $result['permissions']['defaultMode']);
    }

    public function testGenerateSettingsJsonIncludesModel(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
        ]);
        $project->setAiOptions(['model' => 'sonnet']);

        $result = $this->service->generateSettingsJson($project);

        $this->assertSame('sonnet', $result['model']);
    }

    public function testGenerateSettingsJsonIncludesAllowedTools(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
        ]);
        $project->setAiOptions(['allowedTools' => 'Read, Glob, Grep']);

        $result = $this->service->generateSettingsJson($project);

        $this->assertSame(['Read', 'Glob', 'Grep'], $result['allowedTools']);
    }

    public function testGenerateSettingsJsonReturnsEmptyWhenNoOptions(): void
    {
        $project = new Project([
            'name' => 'Test Project',
            'user_id' => 100,
            'ai_options' => null,
        ]);

        $result = $this->service->generateSettingsJson($project);

        $this->assertSame([], $result);
    }

    public function testGetDefaultWorkspacePathCreatesDirectory(): void
    {
        // Clean up first
        $defaultPath = $this->testBasePath . '/default';
        if (is_dir($defaultPath)) {
            $this->removeDirectory($defaultPath);
        }

        $path = $this->service->getDefaultWorkspacePath();

        $this->assertTrue(is_dir($path));
        $this->assertFileExists($path . '/CLAUDE.md');
        $this->assertFileExists($path . '/.claude/settings.local.json');
    }
}
