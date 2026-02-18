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
        $service = new PathService([
            '/home/user/projects' => '/projects',
            '/c/www' => '/projects_2',
        ]);

        $this->assertSame('/projects/my-app', $service->translatePath('/home/user/projects/my-app'));
        $this->assertSame('/projects_2/ice/lvs-bes', $service->translatePath('/c/www/ice/lvs-bes'));
    }

    public function testTranslatePathReturnsOriginalWhenNoMappingMatches(): void
    {
        $service = new PathService(['/home/user/projects' => '/projects']);

        $this->assertSame('/other/path', $service->translatePath('/other/path'));
    }

    public function testTranslatePathWithEmptyMappings(): void
    {
        $this->assertSame('/some/path', $this->service->translatePath('/some/path'));
    }

    public function testTranslatePathUsesFirstMatchingMapping(): void
    {
        $service = new PathService([
            '/home/user' => '/mapped_user',
            '/home/user/projects' => '/mapped_projects',
        ]);

        $this->assertSame('/mapped_user/projects/app', $service->translatePath('/home/user/projects/app'));
    }

    public function testCollectPathsTranslatesRootDirectoryAutomatically(): void
    {
        $this->root->addChild(new vfsStreamFile('file.txt'));

        $service = new PathService(['/host/project' => $this->root->url()]);

        $paths = $service->collectPaths('/host/project', false);

        $this->assertSame(['file.txt'], $paths);
    }

    public function testResolveRequestedPathTranslatesRootDirectoryAutomatically(): void
    {
        $dir = new vfsStreamDirectory('sub');
        $dir->addChild(new vfsStreamFile('readme.md'));
        $this->root->addChild($dir);

        $service = new PathService(['/host/project' => $this->root->url()]);

        $resolved = $service->resolveRequestedPath('/host/project', 'sub/readme.md');

        $this->assertSame($this->root->url() . '/sub/readme.md', $resolved);
    }

    public function testTranslatePathReturnsPathUnchangedWhenAlreadyTranslated(): void
    {
        $service = new PathService(['/host/project' => '/container/project']);

        $translated = $service->translatePath('/container/project/src/file.php');

        $this->assertSame('/container/project/src/file.php', $translated);
    }

    public function testCollectPathsWithMultipleBlacklistRulesAndExceptions(): void
    {
        $packages = new vfsStreamDirectory('packages');
        $packagesBin = new vfsStreamDirectory('bin');
        $packagesLib = new vfsStreamDirectory('lib');
        $packagesBin->addChild(new vfsStreamFile('tool'));
        $packagesLib->addChild(new vfsStreamFile('library.php'));
        $packages->addChild($packagesBin);
        $packages->addChild($packagesLib);

        $tests = new vfsStreamDirectory('tests');
        $testsUnit = new vfsStreamDirectory('unit');
        $testsOutput = new vfsStreamDirectory('_output');
        $testsUnit->addChild(new vfsStreamFile('Test.php'));
        $testsOutput->addChild(new vfsStreamFile('coverage.xml'));
        $tests->addChild($testsUnit);
        $tests->addChild($testsOutput);

        $this->root->addChild($packages);
        $this->root->addChild($tests);

        $blacklist = [
            ['path' => 'packages', 'exceptions' => ['bin']],
            ['path' => 'tests', 'exceptions' => ['unit']],
        ];

        $paths = $this->service->collectPaths($this->root->url(), false, [], $blacklist);

        $this->assertContains('packages/bin/tool', $paths);
        $this->assertNotContains('packages/lib/library.php', $paths);
        $this->assertContains('tests/unit/Test.php', $paths);
        $this->assertNotContains('tests/_output/coverage.xml', $paths);
    }

    public function testCollectPathsAlwaysSkipsBuiltInDirectories(): void
    {
        $vendor = new vfsStreamDirectory('vendor');
        $vendor->addChild(new vfsStreamFile('autoload.php'));
        $nodeModules = new vfsStreamDirectory('node_modules');
        $nodeModules->addChild(new vfsStreamFile('package.json'));
        $git = new vfsStreamDirectory('.git');
        $git->addChild(new vfsStreamFile('HEAD'));
        $svn = new vfsStreamDirectory('.svn');
        $svn->addChild(new vfsStreamFile('entries'));
        $hg = new vfsStreamDirectory('.hg');
        $hg->addChild(new vfsStreamFile('dirstate'));
        $idea = new vfsStreamDirectory('.idea');
        $idea->addChild(new vfsStreamFile('workspace.xml'));
        $vscode = new vfsStreamDirectory('.vscode');
        $vscode->addChild(new vfsStreamFile('settings.json'));
        $src = new vfsStreamDirectory('src');
        $src->addChild(new vfsStreamFile('App.php'));
        $this->root->addChild($vendor);
        $this->root->addChild($nodeModules);
        $this->root->addChild($git);
        $this->root->addChild($svn);
        $this->root->addChild($hg);
        $this->root->addChild($idea);
        $this->root->addChild($vscode);
        $this->root->addChild($src);

        $paths = $this->service->collectPaths($this->root->url(), false);

        $this->assertContains('src/App.php', $paths);
        $this->assertNotContains('vendor/autoload.php', $paths);
        $this->assertNotContains('node_modules/package.json', $paths);
        $this->assertNotContains('.git/HEAD', $paths);
        $this->assertNotContains('.svn/entries', $paths);
        $this->assertNotContains('.hg/dirstate', $paths);
        $this->assertNotContains('.idea/workspace.xml', $paths);
        $this->assertNotContains('.vscode/settings.json', $paths);
    }
}
