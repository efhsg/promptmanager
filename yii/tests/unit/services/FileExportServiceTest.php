<?php

namespace tests\unit\services;

use app\models\Project;
use app\services\CopyFormatConverter;
use app\services\FileExportService;
use app\services\PathService;
use Codeception\Test\Unit;
use common\enums\CopyType;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use tests\fixtures\UserFixture;

class FileExportServiceTest extends Unit
{
    private FileExportService $service;
    private CopyFormatConverter $formatConverter;
    private PathService $pathService;
    private vfsStreamDirectory $root;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatConverter = new CopyFormatConverter();
        $this->pathService = new PathService();
        $this->service = new FileExportService($this->formatConverter, $this->pathService);
        $this->root = vfsStream::setup('project');
    }

    public function testSanitizeFilenameRemovesPathSeparators(): void
    {
        $result = $this->service->sanitizeFilename('path/to\\file');
        $this->assertSame('path-to-file', $result);
    }

    public function testSanitizeFilenameRemovesSpecialCharacters(): void
    {
        $result = $this->service->sanitizeFilename('file:name*with?"chars<>|');
        $this->assertSame('file-name-with--chars---', $result);
    }

    public function testSanitizeFilenameTrimsWhitespaceAndDots(): void
    {
        $result = $this->service->sanitizeFilename('  ..filename..  ');
        $this->assertSame('filename', $result);
    }

    public function testSanitizeFilenameTruncatesLongNames(): void
    {
        $longName = str_repeat('a', 250);
        $result = $this->service->sanitizeFilename($longName);
        $this->assertSame(200, mb_strlen($result));
    }

    public function testSanitizeFilenameReturnsEmptyForInvalidInput(): void
    {
        $result = $this->service->sanitizeFilename('...');
        $this->assertSame('', $result);
    }

    public function testGetExtensionReturnsCorrectExtensions(): void
    {
        $this->assertSame('.md', $this->service->getExtension(CopyType::MD));
        $this->assertSame('.txt', $this->service->getExtension(CopyType::TEXT));
        $this->assertSame('.html', $this->service->getExtension(CopyType::HTML));
        $this->assertSame('.json', $this->service->getExtension(CopyType::QUILL_DELTA));
        $this->assertSame('.xml', $this->service->getExtension(CopyType::LLM_XML));
    }

    public function testExportToFileFailsWithInvalidProjectId(): void
    {
        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"test\n"}]}',
            CopyType::MD,
            'test',
            '/',
            999999,
            1,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Project not found.', $result['message']);
    }

    public function testExportToFileFailsWithEmptyFilename(): void
    {
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"test\n"}]}',
            CopyType::MD,
            '...',
            '/',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid filename.', $result['message']);
    }

    public function testExportToFileFailsWhenProjectHasNoRootDirectory(): void
    {
        $project = $this->createProjectWithoutRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"test\n"}]}',
            CopyType::MD,
            'test',
            '/',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Project has no root directory configured.', $result['message']);
    }

    public function testExportToFileWritesFileSuccessfully(): void
    {
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"Hello World\n"}]}',
            CopyType::MD,
            'export',
            '/',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertTrue($result['success']);
        $this->assertSame('/export.md', $result['path']);
        $this->assertSame('File saved successfully.', $result['message']);
        $this->assertTrue($this->root->hasChild('export.md'));
        $this->assertSame('Hello World', $this->root->getChild('export.md')->getContent());
    }

    public function testExportToFileInSubdirectory(): void
    {
        vfsStream::newDirectory('docs')->at($this->root);
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"Test content\n"}]}',
            CopyType::TEXT,
            'readme',
            '/docs',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertTrue($result['success']);
        $this->assertSame('/docs/readme.txt', $result['path']);
        $this->assertTrue($this->root->hasChild('docs/readme.txt'));
    }

    public function testExportToFileDetectsExistingFile(): void
    {
        vfsStream::newFile('existing.md')->at($this->root)->setContent('old content');
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"new content\n"}]}',
            CopyType::MD,
            'existing',
            '/',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['exists']);
        $this->assertSame('/existing.md', $result['path']);
        $this->assertSame('File already exists.', $result['message']);
        $this->assertSame('old content', $this->root->getChild('existing.md')->getContent());
    }

    public function testExportToFileOverwritesExistingFile(): void
    {
        vfsStream::newFile('existing.md')->at($this->root)->setContent('old content');
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"new content\n"}]}',
            CopyType::MD,
            'existing',
            '/',
            $project->id,
            $project->user_id,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame('/existing.md', $result['path']);
        $this->assertSame('new content', $this->root->getChild('existing.md')->getContent());
    }

    public function testExportToFileFailsForNonExistentDirectory(): void
    {
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"test\n"}]}',
            CopyType::MD,
            'test',
            '/nonexistent',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('directory', strtolower($result['message']));
    }

    public function testExportToFileRejectsPathTraversal(): void
    {
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"malicious content\n"}]}',
            CopyType::MD,
            'evil',
            '/../../../tmp',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function testExportToFileRejectsTraversalWithinPath(): void
    {
        vfsStream::newDirectory('docs')->at($this->root);
        $project = $this->createProjectWithRoot();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"sneaky content\n"}]}',
            CopyType::MD,
            'escape',
            '/docs/../../escape',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
    }

    public function testExportToFileRejectsBlacklistedDirectory(): void
    {
        vfsStream::newDirectory('.git')->at($this->root);
        $project = $this->createProjectWithBlacklist();

        $result = $this->service->exportToFile(
            '{"ops":[{"insert":"test\n"}]}',
            CopyType::MD,
            'hook',
            '/.git',
            $project->id,
            $project->user_id,
            false
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('blacklisted', strtolower($result['message']));
    }

    private function createProjectWithRoot(): Project
    {
        $project = new Project([
            'user_id' => 1,
            'name' => 'Test Project',
            'root_directory' => $this->root->url(),
        ]);
        $project->save(false);

        return $project;
    }

    private function createProjectWithoutRoot(): Project
    {
        $project = new Project([
            'user_id' => 1,
            'name' => 'Test Project Without Root',
            'root_directory' => null,
        ]);
        $project->save(false);

        return $project;
    }

    private function createProjectWithBlacklist(): Project
    {
        $project = new Project([
            'user_id' => 1,
            'name' => 'Test Project With Blacklist',
            'root_directory' => $this->root->url(),
            'blacklisted_directories' => '.git,node_modules',
        ]);
        $project->save(false);

        return $project;
    }
}
