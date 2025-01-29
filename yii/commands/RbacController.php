<?php

namespace app\commands;

use app\rbac\FieldOwnerRule;
use Exception;
use Yii;
use yii\console\Controller;

class RbacController extends Controller
{
    /**
     * Initializes RBAC roles, permissions, and rules.
     * @throws Exception
     */
    public function actionInit(): void
    {
        $auth = Yii::$app->authManager;

        // Remove all existing data
        $auth->removeAll();

        // Add the rule
        $rule = new FieldOwnerRule();
        $auth->add($rule);

        // Create permissions
        $createField = $auth->createPermission('createField');
        $createField->description = 'Create a Field';
        $auth->add($createField);

        $viewField = $auth->createPermission('viewField');
        $viewField->description = 'View a Field';
        $auth->add($viewField);

        $updateField = $auth->createPermission('updateField');
        $updateField->description = 'Update a Field';
        $auth->add($updateField);

        $deleteField = $auth->createPermission('deleteField');
        $deleteField->description = 'Delete a Field';
        $auth->add($deleteField);

        // Assign rules to view, update, and delete permissions
        $viewField->ruleName = $rule->name;
        $auth->update('viewField', $viewField);

        $updateField->ruleName = $rule->name;
        $auth->update('updateField', $updateField);

        $deleteField->ruleName = $rule->name;
        $auth->update('deleteField', $deleteField);

        // Create roles
        $userRole = $auth->createRole('user');
        $auth->add($userRole);

        // Assign permissions to roles
        $auth->addChild($userRole, $createField);
        $auth->addChild($userRole, $viewField);
        $auth->addChild($userRole, $updateField);
        $auth->addChild($userRole, $deleteField);

        // Optionally, create an admin role with all permissions
        $adminRole = $auth->createRole('admin');
        $auth->add($adminRole);
        $auth->addChild($adminRole, $userRole);
        // Admins can have additional permissions or inherit all from 'user'

        echo "RBAC initialization complete.\n";
    }
}
