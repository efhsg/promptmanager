<?php /** @noinspection PhpSameParameterValueInspection */

/** @noinspection PhpExpressionResultUnusedInspection */

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use ReflectionClass;
use ReflectionException;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;

class ProjectControllerCest
{
    private int $userId = 1;

    /**
     * @throws ModuleException
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveFixtures([
            'user'            => UserFixture::class,
            'auth_rule'       => AuthRuleFixture::class,
            'auth_item'       => AuthItemFixture::class,
            'auth_item_child' => AuthItemChildFixture::class,
            'auth_assignment' => AuthAssignmentFixture::class,
            'projects'        => ProjectFixture::class,
        ]);

        $I->amLoggedInAs($this->userId);
    }

    public function testIndexDisplaysAllProjects(FunctionalTester $I): void
    {
        $I->amOnRoute('/project/index');
        $I->seeResponseCodeIs(200);
        $I->see('Test Project 2');
    }

    public function testViewDisplaysCorrectProject(FunctionalTester $I): void
    {
        // Viewing the project owned by user 1 (project id=2)
        $I->amOnRoute('/project/view', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Project Details');
        $I->see('Test Project 2');
    }

    public function testCreateNewProjectSuccessfully(FunctionalTester $I): void
    {
        $I->amOnRoute('/project/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Project');

        // Submit the create form with valid data.
        $I->submitForm('#project-form', [
            'Project[name]' => 'NewProject',
            'Project[description]' => 'A new project description',
        ]);

        $I->seeResponseCodeIsSuccessful();
        // Verify that the project was saved in the database with user_id=1.
        $I->seeInDatabase('project', [
            'name'    => 'NewProject',
            'user_id' => $this->userId,
        ]);
        $I->see('Project Details');
        $I->see('NewProject');
    }

    public function testCreateProjectWithInvalidDataFails(FunctionalTester $I): void
    {
        $I->amOnRoute('/project/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Project');

        // Submit the create form with invalid (empty) data.
        $I->submitForm('#project-form', [
            'Project[name]' => '',
        ]);

        $I->seeResponseCodeIsSuccessful();
        // Expect validation errors to appear.
        $I->see('Create Project');
        $I->see('Name cannot be blank.');
    }

    public function testUpdateExistingProjectSuccessfully(FunctionalTester $I): void
    {
        // Updating project with id=2 which belongs to user 1.
        $I->amOnRoute('/project/update', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Update');
        $I->see('Project 2');

        $I->submitForm('#project-form', [
            'Project[name]' => 'UpdatedProject',
            'Project[description]' => 'Updated description',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->seeInDatabase('project', ['id' => 2, 'name' => 'UpdatedProject']);
        $I->see('Project Details');
        $I->see('UpdatedProject');
    }

    public function testUpdateProjectWithInvalidDataFails(FunctionalTester $I): void
    {
        $I->amOnRoute('/project/update', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Update');
        $I->see('Project 2');

        $I->submitForm('#project-form', [
            'Project[name]' => '',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('Update');
        $I->see('Project 2');
        $I->see('Name cannot be blank.');
    }

    public function testDeleteProjectSuccessfully(FunctionalTester $I): void
    {
        // Deleting project with id=2 (owned by user 1).
        $I->amOnRoute('/project/delete', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        $I->submitForm('#delete-confirmation-form', [
            'confirm' => 1,
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->dontSeeInDatabase('project', ['id' => 2]);
        $I->see('Projects'); // Assuming the index page shows "Projects"
    }

    public function testDeleteProjectWithoutConfirmationFails(FunctionalTester $I): void
    {
        // Display the delete confirmation for project id=2.
        $I->amOnRoute('/project/delete', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        // Simulate cancellation (e.g. clicking "Cancel").
        $I->click('Cancel');
        $I->seeResponseCodeIsSuccessful();
        $I->seeInDatabase('project', ['id' => 2]);
        $I->see('Projects');
    }

    public function testDeleteActionRequiresPost(FunctionalTester $I): void
    {
        // First, display the confirmation for project id=2.
        $I->amOnRoute('/project/delete', ['id' => 2]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        // Now simulate a GET request without confirmation.
        $I->amOnRoute('/project/delete', ['id' => 2]);
        $I->seeInDatabase('project', ['id' => 2]);
    }

    public function testSetCurrentProject(FunctionalTester $I): void
    {
        $I->amOnPage('/');
        $I->submitForm('#set-context-project-form', [
            'project_id' => 2,
        ]);
        $I->seeInField('#set-context-project-form select[name="project_id"]', '2');
    }

    public function testUserCannotAccessProjectsOfOtherUsers(FunctionalTester $I): void
    {
        // Attempting to access project id=1 which belongs to user 100 should fail.
        $I->amOnRoute('/project/view', ['id' => 1]);
        $I->seeResponseCodeIs(404);
        $I->see('The requested Project does not exist or is not yours.');
    }

    public function testUnauthenticatedUserIsRedirected(FunctionalTester $I): void
    {
        Yii::$app->user->logout();

        $restrictedRoutes = [
            '/project/view?id=2',
            '/project/create',
            '/project/update?id=2',
            '/project/delete?id=2',
            '/project/set-current',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeInCurrentUrl('/identity/auth/login');
        }
    }

    public function testNonExistentProjectAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/project/view', ['id' => 9999]);
        $I->seeResponseCodeIs(404);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidConfigException
     */
    public function testFindModelReturnsProject(FunctionalTester $I): void
    {
        $controller = Yii::$app->createController('project')[0];

        // Using project id=2 since that's owned by user 1.
        $model = $this->invokeMethod($controller, 'findModel', [2]);

        $I->assertNotNull($model, 'findModel should return a valid model instance');
        $I->assertEquals(2, $model->id, 'Model ID should match the requested ID');
    }

    /**
     * @throws ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
