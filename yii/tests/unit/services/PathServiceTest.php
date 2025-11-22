<?php

namespace tests\unit\services;

use app\services\PathService;
use Codeception\Test\Unit;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use UnexpectedValueException;

class PathServiceTest extends Unit
{
    private PathService $service;

    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PathService();
        $this->root = vfsStream::setup('root');
    }

    public function testCollectPathsReturnsDirectoriesOnly(): void
    {
        $nested = new vfsStreamDirectory('nested');
        $sub = new vfsStreamDirectory('sub');
        $sub->addChild($nested);
        $this->root->addChild($sub);
        $this->root->addChild(new vfsStreamFile('ignored.txt'));

        $paths = $this->service->collectPaths($this->root->url(), true);

        $this->assertSame(['/', 'sub', 'sub/nested'], $paths);
    }

    public function testCollectPathsFiltersFilesByAllowedExtensions(): void
    {
        $nested = new vfsStreamDirectory('nested');
        $nested->addChild(new vfsStreamFile('readme.TXT'));
        $this->root->addChild($nested);
        $this->root->addChild(new vfsStreamFile('keep.txt'));
        $this->root->addChild(new vfsStreamFile('skip.log'));
        $this->root->addChild(new vfsStreamFile('noext'));

        $paths = $this->service->collectPaths($this->root->url(), false, ['txt']);

        $this->assertSame(['keep.txt', 'nested/readme.TXT'], $paths);
    }

    public function testCollectPathsThrowsWhenRootMissing(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->service->collectPaths($this->root->url() . '/missing', true);
    }

    public function testResolveRequestedPathNormalizesPathWithinRoot(): void
    {
        $dir = new vfsStreamDirectory('dir');
        $file = new vfsStreamFile('file.txt');
        $dir->addChild($file);
        $this->root->addChild($dir);

        $resolved = $this->service->resolveRequestedPath($this->root->url(), 'dir\\file.txt');

        $this->assertSame($this->root->url() . '/dir/file.txt', $resolved);
    }
}
