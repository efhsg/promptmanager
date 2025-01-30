<?php
return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'rbac' => [
        'entities' => [
            'field' => [
                'actionPermissionMap' => [
                    'create' => 'createField',
                    'view' => 'viewField',
                    'update' => 'updateField',
                    'delete' => 'deleteField',
                ],
                'permissions' => [
                    'createField' => [
                        'description' => 'Create a Field',
                        'rule' => null,
                    ],
                    'viewField' => [
                        'description' => 'View a Field',
                        'rule' => 'app\rbac\FieldOwnerRule',
                    ],
                    'updateField' => [
                        'description' => 'Update a Field',
                        'rule' => 'app\rbac\FieldOwnerRule',
                    ],
                    'deleteField' => [
                        'description' => 'Delete a Field',
                        'rule' => 'app\rbac\FieldOwnerRule',
                    ],
                ],
            ],
        ],
        'roles' => [
            'user' => [
                'permissions' => [
                    'createField',
                    'viewField',
                    'updateField',
                    'deleteField',
                ],
                'children' => [],
            ],
            'admin' => [
                'permissions' => [],
                'children' => ['user'],
            ],
        ],
    ],
];
