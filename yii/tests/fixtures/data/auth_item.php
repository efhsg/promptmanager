<?php

return [
    [
        'name' => 'createField',
        'type' => 2,  // permission
        'description' => 'Create a Field',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewField',
        'type' => 2,
        'description' => 'View a Field',
        'rule_name' => 'fieldOwner', // Associates the rule
        'data' => null,
    ],
    [
        'name' => 'updateField',
        'type' => 2,
        'description' => 'Update a Field',
        'rule_name' => 'fieldOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteField',
        'type' => 2,
        'description' => 'Delete a Field',
        'rule_name' => 'fieldOwner',
        'data' => null,
    ],

    [
        'name' => 'createProject',
        'type' => 2,
        'description' => 'Create a Project',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewProject',
        'type' => 2,
        'description' => 'View a Project',
        'rule_name' => 'projectOwner',
        'data' => null,
    ],
    [
        'name' => 'updateProject',
        'type' => 2,
        'description' => 'Update a Project',
        'rule_name' => 'projectOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteProject',
        'type' => 2,
        'description' => 'Delete a Project',
        'rule_name' => 'projectOwner',
        'data' => null,
    ],
    [
        'name' => 'setCurrentProject',
        'type' => 2,
        'description' => 'Set Current Project',
        'rule_name' => null,
        'data' => null,
    ],

    [
        'name' => 'createContext',
        'type' => 2,
        'description' => 'Create a Context',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewContext',
        'type' => 2,
        'description' => 'View a Context',
        'rule_name' => 'contextOwner',
        'data' => null,
    ],
    [
        'name' => 'updateContext',
        'type' => 2,
        'description' => 'Update a Context',
        'rule_name' => 'contextOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteContext',
        'type' => 2,
        'description' => 'Delete a Context',
        'rule_name' => 'contextOwner',
        'data' => null,
    ],


    // Roles
    [
        'name' => 'user',
        'type' => 1,
        'description' => 'Regular User',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'admin',
        'type' => 1,
        'description' => 'Administrator',
        'rule_name' => null,
        'data' => null,
    ],
];
