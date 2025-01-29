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

    // Roles
    [
        'name' => 'user',
        'type' => 1, // Role
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
