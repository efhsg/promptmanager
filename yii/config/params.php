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
                    'view'   => 'viewField',
                    'update' => 'updateField',
                    'delete' => 'deleteField',
                ],
                'permissions' => [
                    'createField' => [
                        'description' => 'Create a Field',
                        'rule'        => null,
                    ],
                    'viewField' => [
                        'description' => 'View a Field',
                        'rule'        => 'app\rbac\FieldOwnerRule',
                    ],
                    'updateField' => [
                        'description' => 'Update a Field',
                        'rule'        => 'app\rbac\FieldOwnerRule',
                    ],
                    'deleteField' => [
                        'description' => 'Delete a Field',
                        'rule'        => 'app\rbac\FieldOwnerRule',
                    ],
                ],
            ],
            'project' => [
                'actionPermissionMap' => [
                    'create' => 'createProject',
                    'view'   => 'viewProject',
                    'update' => 'updateProject',
                    'delete' => 'deleteProject',
                ],
                'permissions' => [
                    'createProject' => [
                        'description' => 'Create a Project',
                        'rule'        => null,
                    ],
                    'viewProject' => [
                        'description' => 'View a Project',
                        'rule'        => 'app\rbac\ProjectOwnerRule',
                    ],
                    'updateProject' => [
                        'description' => 'Update a Project',
                        'rule'        => 'app\rbac\ProjectOwnerRule',
                    ],
                    'deleteProject' => [
                        'description' => 'Delete a Project',
                        'rule'        => 'app\rbac\ProjectOwnerRule',
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
                    'createProject',
                    'viewProject',
                    'updateProject',
                    'deleteProject',
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
