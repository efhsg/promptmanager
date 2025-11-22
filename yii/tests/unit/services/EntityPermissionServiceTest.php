<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace tests\unit\services;

use app\services\EntityPermissionService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\caching\ArrayCache;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\rbac\ManagerInterface;
use yii\rbac\Permission;
use yii\web\User;

class EntityPermissionServiceTest extends Unit
{
    private EntityPermissionService $service;

    private ArrayCache $cache;

    private ManagerInterface&MockObject $authManager;

    private User&MockObject $user;

    private ?CacheInterface $originalCache = null;

    private ?ManagerInterface $originalAuthManager = null;

    private ?User $originalUser = null;

    private array $originalParams = [];

    protected function _before(): void
    {
        parent::_before();
        $this->originalCache = Yii::$app->cache;
        $this->originalAuthManager = Yii::$app->authManager;
        $this->originalUser = Yii::$app->user;
        $this->originalParams = Yii::$app->params;

        $this->cache = new ArrayCache();
        Yii::$app->set('cache', $this->cache);

        $this->authManager = $this->getMockBuilder(ManagerInterface::class)->getMock();
        Yii::$app->set('authManager', $this->authManager);

        $this->user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can', 'getId'])
            ->getMock();
        $this->user->method('getId')->willReturn(42);
        Yii::$app->set('user', $this->user);

        TagDependency::invalidate($this->cache, 'user_permissions');
        $this->service = new EntityPermissionService();
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', $this->originalCache);
        Yii::$app->set('authManager', $this->originalAuthManager);
        Yii::$app->set('user', $this->originalUser);
        Yii::$app->params = $this->originalParams;
        parent::_after();
    }

    public function testGetActionPermissionMapCachesExistingPermissions(): void
    {
        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'view' => 'entity.view',
                    'editItem' => 'entity.edit',
                    'missing' => 'entity.missing',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->exactly(3))
            ->method('getPermission')
            ->willReturnCallback(function (string $permission) {
                if (in_array($permission, ['entity.view', 'entity.edit'], true)) {
                    $perm = $this->createMock(Permission::class);
                    $perm->method('__get')->willReturn($permission);
                    return $perm;
                }
                return null;
            });

        $expected = [
            'view' => 'entity.view',
            'edit-item' => 'entity.edit',
        ];

        $first = $this->service->getActionPermissionMap('entity');
        $this->assertSame($expected, $first);

        $second = $this->service->getActionPermissionMap('entity');
        $this->assertSame($expected, $second);
    }

    public function testIsModelBasedAction(): void
    {
        $this->assertTrue($this->service->isModelBasedAction('view'));
        $this->assertTrue($this->service->isModelBasedAction('update'));
        $this->assertTrue($this->service->isModelBasedAction('delete'));
        $this->assertFalse($this->service->isModelBasedAction('index'));
    }

    public function testHasActionPermissionForNonModelAction(): void
    {
        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'index' => 'entity.index',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->once())
            ->method('getPermission')
            ->with('entity.index')
            ->willReturn($this->createMock(Permission::class));

        $this->user
            ->expects($this->once())
            ->method('can')
            ->with('entity.index')
            ->willReturn(true);

        $this->assertTrue($this->service->hasActionPermission('entity', 'index'));
    }

    public function testHasActionPermissionReturnsFalseForUnknownAction(): void
    {
        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'view' => 'entity.view',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->once())
            ->method('getPermission')
            ->with('entity.view')
            ->willReturn($this->createMock(Permission::class));

        $this->user->expects($this->never())->method('can');

        $this->assertFalse($this->service->hasActionPermission('entity', 'export'));
    }

    public function testHasActionPermissionWithoutCallbackForModelAction(): void
    {
        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'view' => 'entity.view',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->once())
            ->method('getPermission')
            ->with('entity.view')
            ->willReturn($this->createMock(Permission::class));

        $this->user->expects($this->never())->method('can');

        $this->assertFalse($this->service->hasActionPermission('entity', 'view'));
    }

    public function testHasActionPermissionWithModelCallback(): void
    {
        $model = $this->createActiveRecordMock(5);

        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'update' => 'entity.update',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->once())
            ->method('getPermission')
            ->with('entity.update')
            ->willReturn($this->createMock(Permission::class));

        $this->user
            ->expects($this->once())
            ->method('can')
            ->with('entity.update', ['model' => $model])
            ->willReturn(true);

        $callback = static fn(): ActiveRecord => $model;
        $this->assertTrue($this->service->hasActionPermission('entity', 'update', $callback));
    }

    public function testHasActionPermissionRejectsNonModelCallbackResult(): void
    {
        $this->setRbacParams([
            'entity' => [
                'actionPermissionMap' => [
                    'view' => 'entity.view',
                ],
            ],
        ]);

        $this->authManager
            ->expects($this->once())
            ->method('getPermission')
            ->with('entity.view')
            ->willReturn($this->createMock(Permission::class));

        $this->user->expects($this->never())->method('can');

        $this->assertFalse(
            $this->service->hasActionPermission('entity', 'view', static fn(): string => 'not-a-model')
        );
    }

    public function testCheckPermissionCachesResultWithoutModel(): void
    {
        $this->cache->set('rbac_version', 1);

        $this->user
            ->expects($this->once())
            ->method('can')
            ->with('entity.list')
            ->willReturn(true);

        $first = $this->service->checkPermission('entity.list');
        $second = $this->service->checkPermission('entity.list');

        $this->assertTrue($first);
        $this->assertTrue($second);
    }

    public function testCheckPermissionCachesByModelAndInvalidatesWithVersion(): void
    {
        $this->cache->set('rbac_version', 1);
        $model = $this->createActiveRecordMock(10);

        $this->user
            ->expects($this->exactly(2))
            ->method('can')
            ->willReturnCallback(function (string $permission, array $params) use ($model): bool {
                $this->assertSame('entity.view', $permission);
                $this->assertSame(['model' => $model], $params);
                static $call = 0;
                $call++;
                return $call === 1;
            });

        $first = $this->service->checkPermission('entity.view', $model);
        EntityPermissionService::invalidatePermissionCache();
        $second = $this->service->checkPermission('entity.view', $model);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function testCheckPermissionSupportsCompositePrimaryKeys(): void
    {
        $this->cache->set('rbac_version', 1);
        $model = $this->createActiveRecordMock(['user_id' => 10, 'group_id' => 5]);

        $this->user
            ->expects($this->once())
            ->method('can')
            ->with('entity.update', ['model' => $model])
            ->willReturn(true);

        $result = $this->service->checkPermission('entity.update', $model);
        $this->assertTrue($result);

        $this->assertTrue(
            $this->cache->exists('permission_check_42_entity.update_model_10_5_1')
        );
    }

    public function testCheckPermissionUsesObjectHashWhenModelHasNoPrimaryKey(): void
    {
        $this->cache->set('rbac_version', 1);

        /** @var ActiveRecord&MockObject $model */
        $model = $this->getMockBuilder(ActiveRecord::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrimaryKey'])
            ->getMock();
        $model->method('getPrimaryKey')->willReturn(null);

        $this->user
            ->expects($this->once())
            ->method('can')
            ->with('entity.create', ['model' => $model])
            ->willReturn(true);

        $first = $this->service->checkPermission('entity.create', $model);
        $second = $this->service->checkPermission('entity.create', $model);

        $this->assertTrue($first);
        $this->assertTrue($second);
    }

    public function testRevokeAllUserPermissionsInvokesAuthManagerAndInvalidatesCache(): void
    {
        $this->cache->set('rbac_version', 1);

        $this->authManager
            ->expects($this->once())
            ->method('revokeAll')
            ->with(77);

        $this->service->revokeAllUserPermissions(77);

        $this->assertNotSame(1, $this->cache->get('rbac_version'));
    }

    private function setRbacParams(array $entities): void
    {
        Yii::$app->params['rbac']['entities'] = $entities;
    }

    /**
     * @return ActiveRecord&MockObject
     */
    private function createActiveRecordMock(int|array $primaryKey): ActiveRecord
    {
        /** @var ActiveRecord&MockObject $model */
        $model = $this->getMockBuilder(ActiveRecord::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrimaryKey'])
            ->getMock();
        $model->method('getPrimaryKey')->willReturn($primaryKey);

        return $model;
    }
}
