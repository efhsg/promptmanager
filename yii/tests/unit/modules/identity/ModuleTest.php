<?php

namespace tests\unit\modules\identity;

use app\modules\identity\Module;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\base\Application as BaseApplication;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

class ModuleTest extends Unit
{
    private ?BaseApplication $originalApp = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalApp = Yii::$app;
    }

    protected function tearDown(): void
    {
        Yii::$app = $this->originalApp;
        parent::tearDown();
    }

    public function testInitKeepsControllerNamespaceForWebApplication(): void
    {
        /** @var MockObject&WebApplication $webApp */
        $webApp = $this->getMockBuilder(WebApplication::class)
            ->disableOriginalConstructor()
            ->getMock();

        Yii::$app = $webApp;

        $module = new Module('identity');

        self::assertSame('app\modules\identity\controllers', $module->controllerNamespace);
    }

    public function testInitUsesConsoleControllerNamespaceForConsoleApplication(): void
    {
        /** @var MockObject&ConsoleApplication $consoleApp */
        $consoleApp = $this->getMockBuilder(ConsoleApplication::class)
            ->disableOriginalConstructor()
            ->getMock();

        Yii::$app = $consoleApp;

        $module = new Module('identity');

        self::assertSame('app\modules\identity\commands', $module->controllerNamespace);
    }
}
