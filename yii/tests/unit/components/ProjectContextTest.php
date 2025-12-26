<?php

namespace tests\unit\components;

use app\components\ProjectContext;
use app\services\UserPreferenceService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Throwable;
use yii\db\StaleObjectException;
use yii\web\Session;
use yii\web\User as WebUser;

class ProjectContextTest extends Unit
{
    private Session&MockObject $session;
    private UserPreferenceService&MockObject $userPreference;
    private WebUser&MockObject $webUser;

    protected function _before(): void
    {
        parent::_before();
        $this->session = $this->createMock(Session::class);
        $this->userPreference = $this->createMock(UserPreferenceService::class);
        $this->webUser = $this->createMock(WebUser::class);
    }

    private function createProjectContext(): ProjectContext
    {
        return new ProjectContext($this->session, $this->userPreference, $this->webUser);
    }

    public function testIsNoProjectContextReturnsTrueWhenSessionHasZero(): void
    {
        $this->session->method('get')->willReturn(0);

        $context = $this->createProjectContext();

        $this->assertTrue($context->isNoProjectContext());
    }

    public function testIsNoProjectContextReturnsFalseWhenSessionHasProjectId(): void
    {
        $this->session->method('get')->willReturn(5);

        $context = $this->createProjectContext();

        $this->assertFalse($context->isNoProjectContext());
    }

    public function testIsNoProjectContextReturnsFalseWhenSessionHasAllProjects(): void
    {
        $this->session->method('get')->willReturn(ProjectContext::ALL_PROJECTS_ID);

        $context = $this->createProjectContext();

        $this->assertFalse($context->isNoProjectContext());
    }

    public function testIsNoProjectContextReturnsFalseWhenSessionIsEmpty(): void
    {
        $this->session->method('get')->willReturn(null);

        $context = $this->createProjectContext();

        $this->assertFalse($context->isNoProjectContext());
    }

    public function testIsAllProjectsContextReturnsTrueWhenSessionHasMinusOne(): void
    {
        $this->session->method('get')->willReturn(ProjectContext::ALL_PROJECTS_ID);

        $context = $this->createProjectContext();

        $this->assertTrue($context->isAllProjectsContext());
    }

    public function testIsAllProjectsContextReturnsFalseWhenSessionHasNoProject(): void
    {
        $this->session->method('get')->willReturn(ProjectContext::NO_PROJECT_ID);

        $context = $this->createProjectContext();

        $this->assertFalse($context->isAllProjectsContext());
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function testSetCurrentProjectWithZeroStoresInSession(): void
    {
        $this->webUser->method('__get')->with('isGuest')->willReturn(true);
        $this->session->expects($this->once())
            ->method('set')
            ->with('currentProjectId', ProjectContext::NO_PROJECT_ID);

        $context = $this->createProjectContext();
        $context->setCurrentProject(ProjectContext::NO_PROJECT_ID);
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function testSetCurrentProjectWithZeroSavesToUserPreferencesWhenLoggedIn(): void
    {
        $this->webUser->method('__get')->willReturnMap([
            ['isGuest', false],
            ['id', 100],
        ]);

        $this->session->expects($this->once())
            ->method('set')
            ->with('currentProjectId', ProjectContext::NO_PROJECT_ID);

        $this->userPreference->expects($this->once())
            ->method('setValue')
            ->with(100, 'default_project_id', '0');

        $context = $this->createProjectContext();
        $context->setCurrentProject(ProjectContext::NO_PROJECT_ID);
    }

    public function testGetCurrentProjectReturnsNullWhenNoProjectContext(): void
    {
        $this->session->method('get')->willReturn(ProjectContext::NO_PROJECT_ID);

        $context = $this->createProjectContext();

        $this->assertNull($context->getCurrentProject());
    }

    public function testGetCurrentProjectReturnsNullWhenAllProjectsContext(): void
    {
        $this->session->method('get')->willReturn(ProjectContext::ALL_PROJECTS_ID);

        $context = $this->createProjectContext();

        $this->assertNull($context->getCurrentProject());
    }

    public function testNoProjectIdConstantIsZero(): void
    {
        $this->assertSame(0, ProjectContext::NO_PROJECT_ID);
    }

    public function testAllProjectsIdConstantIsMinusOne(): void
    {
        $this->assertSame(-1, ProjectContext::ALL_PROJECTS_ID);
    }
}
