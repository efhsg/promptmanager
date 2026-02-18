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

    public function testCollectPathsSkipsBlacklistedDirectories(): void
    {
        $vendor = new vfsStreamDirectory('vendor');
        $vendor->addChild(new vfsStreamDirectory('package'));
        $src = new vfsStreamDirectory('src');
        $src->addChild(new vfsStreamFile('keep.md'));
        $this->root->addChild($vendor);
        $this->root->addChild($src);

        $paths = $this->service->collectPaths($this->root->url(), false, [], ['vendor']);

        $this->assertSame(['src/keep.md'], $paths);
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

    public function testResolveRequestedPathReturnsNullForBlacklistedPath(): void
    {
        $logs = new vfsStreamDirectory('logs');
        $logs->addChild(new vfsStreamFile('app.log'));
        $this->root->addChild($logs);

        $resolved = $this->service->resolveRequestedPath(
            $this->root->url(),
            'logs/app.log',
            [['path' => 'logs', 'exceptions' => []]]
        );

        $this->assertNull($resolved);
    }

    public function testCollectPathsRespectsWhitelistExceptions(): void
    {
        $web = new vfsStreamDirectory('web');
        $css = new vfsStreamDirectory('css');
        $js = new vfsStreamDirectory('js');
        $assets = new vfsStreamDirectory('assets');

        $css->addChild(new vfsStreamFile('style.css'));
        $js->addChild(new vfsStreamFile('app.js'));
        $assets->addChild(new vfsStreamFile('bundle.js'));

        $web->addChild($css);
        $web->addChild($js);
        $web->addChild($assets);
        $this->root->addChild($web);

        $blacklist = [
            ['path' => 'web', 'exceptions' => ['css', 'js']],
        ];

        $paths = $this->service->collectPaths($this->root->url(), false, [], $blacklist);

        $this->assertContains('web/css/style.css', $paths);
        $this->assertContains('web/js/app.js', $paths);
        $this->assertNotContains('web/assets/bundle.js', $paths);
    }

    public function testCollectPathsWithBackwardCompatibleStringFormat(): void
    {
        $vendor = new vfsStreamDirectory('vendor');
        $vendor->addChild(new vfsStreamDirectory('package'));
        $src = new vfsStreamDirectory('src');
        $src->addChild(new vfsStreamFile('keep.md'));
        $this->root->addChild($vendor);
        $this->root->addChild($src);

        $paths = $this->service->collectPaths($this->root->url(), false, [], ['vendor']);

        $this->assertSame(['src/keep.md'], $paths);
    }

    public function testResolveRequestedPathAllowsWhitelistedSubdirectory(): void
    {
        $web = new vfsStreamDirectory('web');
        $css = new vfsStreamDirectory('css');
        $assets = new vfsStreamDirectory('assets');

        $cssFile = new vfsStreamFile('style.css');
        $assetFile = new vfsStreamFile('bundle.js');

        $css->addChild($cssFile);
        $assets->addChild($assetFile);
        $web->addChild($css);
        $web->addChild($assets);
        $this->root->addChild($web);

        $blacklist = [
            ['path' => 'web', 'exceptions' => ['css']],
        ];

        $resolvedCss = $this->service->resolveRequestedPath($this->root->url(), 'web/css/style.css', $blacklist);
        $this->assertNotNull($resolvedCss);
        $this->assertSame($this->root->url() . '/web/css/style.css', $resolvedCss);

        $resolvedAsset = $this->service->resolveRequestedPath($this->root->url(), 'web/assets/bundle.js', $blacklist);
        $this->assertNull($resolvedAsset);
    }

    public function testTranslatePathAppliesMapping(): void
    {
        $mappings = [
            '/home/user/projects' => '/projects',
            '/c/www' => '/projects_2',
        ];

        $this->assertSame('/projects/my-app', $this->service->translatePath('/home/user/projects/my-app', $mappings));
        $this->assertSame('/projects_2/ice/lvs-bes', $this->service->translatePath('/c/www/ice/lvs-bes', $mappings));
    }

    public function testTranslatePathReturnsOriginalWhenNoMappingMatches(): void
    {
        $mappings = ['/home/user/projects' => '/projects'];

        $this->assertSame('/other/path', $this->service->translatePath('/other/path', $mappings));
    }

    public function testTranslatePathWithEmptyMappings(): void
    {
        $this->assertSame('/some/path', $this->service->translatePath('/some/path', []));
    }

    public function testTranslatePathUsesFirstMatchingMapping(): void
    {
        $mappings = [
            '/home/user' => '/mapped_user',
            '/home/user/projects' => '/mapped_projects',
        ];

        $this->assertSame('/mapped_user/projects/app', $this->service->translatePath('/home/user/projects/app', $mappings));
    }

    public function testCollectPathsWithMultipleBlacklistRulesAndExceptions(): void
    {
        $vendor = new vfsStreamDirectory('vendor');
        $vendorBin = new vfsStreamDirectory('bin');
        $vendorLib = new vfsStreamDirectory('lib');
        $vendorBin->addChild(new vfsStreamFile('tool'));
        $vendorLib->addChild(new vfsStreamFile('library.php'));
        $vendor->addChild($vendorBin);
        $vendor->addChild($vendorLib);

        $tests = new vfsStreamDirectory('tests');
        $testsUnit = new vfsStreamDirectory('unit');
        $testsOutput = new vfsStreamDirectory('_output');
        $testsUnit->addChild(new vfsStreamFile('Test.php'));
        $testsOutput->addChild(new vfsStreamFile('coverage.xml'));
        $tests->addChild($testsUnit);
        $tests->addChild($testsOutput);

        $this->root->addChild($vendor);
        $this->root->addChild($tests);

        $blacklist = [
            ['path' => 'vendor', 'exceptions' => ['bin']],
            ['path' => 'tests', 'exceptions' => ['unit']],
        ];

        $paths = $this->service->collectPaths($this->root->url(), false, [], $blacklist);

        $this->assertContains('vendor/bin/tool', $paths);
        $this->assertNotContains('vendor/lib/library.php', $paths);
        $this->assertContains('tests/unit/Test.php', $paths);
        $this->assertNotContains('tests/_output/coverage.xml', $paths);
    }
}
