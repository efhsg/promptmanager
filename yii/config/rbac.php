<?php

return [
    'entities' => [
        'field' => [
            'actionPermissionMap' => [
                'create' => 'createField',
                'view' => 'viewField',
                'update' => 'updateField',
                'delete' => 'deleteField',
                'renumber' => 'updateField',
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
        'project' => [
            'actionPermissionMap' => [
                'create' => 'createProject',
                'view' => 'viewProject',
                'update' => 'updateProject',
                'delete' => 'deleteProject',
                'setCurrent' => 'setCurrentProject',
                'claudeCommands' => 'viewProject',
                'claude' => 'viewProject',
            ],
            'permissions' => [
                'createProject' => [
                    'description' => 'Create a Project',
                    'rule' => null,
                ],
                'viewProject' => [
                    'description' => 'View a Project',
                    'rule' => 'app\rbac\ProjectOwnerRule',
                ],
                'updateProject' => [
                    'description' => 'Update a Project',
                    'rule' => 'app\rbac\ProjectOwnerRule',
                ],
                'deleteProject' => [
                    'description' => 'Delete a Project',
                    'rule' => 'app\rbac\ProjectOwnerRule',
                ],
                'setCurrentProject' => [
                    'description' => 'Set Current Project',
                    'rule' => null,
                ],
            ],
        ],
        'context' => [
            'actionPermissionMap' => [
                'create' => 'createContext',
                'view' => 'viewContext',
                'update' => 'updateContext',
                'delete' => 'deleteContext',
            ],
            'permissions' => [
                'createContext' => [
                    'description' => 'Create a Context',
                    'rule' => null,
                ],
                'viewContext' => [
                    'description' => 'View a Context',
                    'rule' => 'app\rbac\ContextOwnerRule',
                ],
                'updateContext' => [
                    'description' => 'Update a Context',
                    'rule' => 'app\rbac\ContextOwnerRule',
                ],
                'deleteContext' => [
                    'description' => 'Delete a Context',
                    'rule' => 'app\rbac\ContextOwnerRule',
                ],
            ],
        ],
        'promptTemplate' => [
            'actionPermissionMap' => [
                'create' => 'createPromptTemplate',
                'view' => 'viewPromptTemplate',
                'update' => 'updatePromptTemplate',
                'delete' => 'deletePromptTemplate',
            ],
            'permissions' => [
                'createPromptTemplate' => [
                    'description' => 'Create a Prompt Template',
                    'rule' => null,
                ],
                'viewPromptTemplate' => [
                    'description' => 'View a Prompt Template',
                    'rule' => 'app\rbac\PromptTemplateOwnerRule',
                ],
                'updatePromptTemplate' => [
                    'description' => 'Update a Prompt Template',
                    'rule' => 'app\rbac\PromptTemplateOwnerRule',
                ],
                'deletePromptTemplate' => [
                    'description' => 'Delete a Prompt Template',
                    'rule' => 'app\rbac\PromptTemplateOwnerRule',
                ],
            ],
        ],
        'promptInstance' => [
            'actionPermissionMap' => [
                'create' => 'createPromptInstance',
                'view' => 'viewPromptInstance',
                'update' => 'updatePromptInstance',
                'delete' => 'deletePromptInstance',
                'generate-prompt-form' => 'generatePrompt',
                'generate-final-prompt' => 'generatePrompt',
                'save-final-prompt' => 'generatePrompt',
                'fetchContent' => 'viewPromptInstance',
            ],
            'permissions' => [
                'createPromptInstance' => [
                    'description' => 'Create a Prompt Instance',
                    'rule' => null,
                ],
                'viewPromptInstance' => [
                    'description' => 'View a Prompt Instance',
                    'rule' => 'app\rbac\PromptInstanceOwnerRule',
                ],
                'updatePromptInstance' => [
                    'description' => 'Update a Prompt Instance',
                    'rule' => 'app\rbac\PromptInstanceOwnerRule',
                ],
                'deletePromptInstance' => [
                    'description' => 'Delete a Prompt Instance',
                    'rule' => 'app\rbac\PromptInstanceOwnerRule',
                ],
                'generatePrompt' => [
                    'description' => 'Generate a Prompt Instance',
                    'rule' => 'app\rbac\PromptTemplateOwnerRule',
                ],
            ],
        ],
        'claude' => [
            'actionPermissionMap' => [
                'index' => 'viewProject',
                'run' => 'viewProject',
                'stream' => 'viewProject',
                'cancel' => 'viewProject',
                'usage' => 'viewProject',
                'checkConfig' => 'viewProject',
                'summarizeSession' => 'viewProject',
                'summarizePrompt' => 'viewProject',
            ],
            // Permissions are defined under 'project' entity — no additional permissions needed.
        ],
        'note' => [
            'actionPermissionMap' => [
                'create' => 'createNote',
                'view' => 'viewNote',
                'update' => 'updateNote',
                'delete' => 'deleteNote',
                'claude' => 'viewNote',
                'fetchContent' => 'viewNote',
            ],
            'permissions' => [
                'createNote' => [
                    'description' => 'Create a Note',
                    'rule' => null,
                ],
                'viewNote' => [
                    'description' => 'View a Note',
                    'rule' => 'app\rbac\NoteOwnerRule',
                ],
                'updateNote' => [
                    'description' => 'Update a Note',
                    'rule' => 'app\rbac\NoteOwnerRule',
                ],
                'deleteNote' => [
                    'description' => 'Delete a Note',
                    'rule' => 'app\rbac\NoteOwnerRule',
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
                'setCurrentProject',
                'createContext',
                'viewContext',
                'updateContext',
                'deleteContext',
                'createPromptTemplate',
                'viewPromptTemplate',
                'updatePromptTemplate',
                'deletePromptTemplate',
                'createPromptInstance',
                'viewPromptInstance',
                'updatePromptInstance',
                'deletePromptInstance',
                'generatePrompt',
                'createNote',
                'viewNote',
                'updateNote',
                'deleteNote',
            ],
            'children' => [],
        ],
        'admin' => [
            'permissions' => [],
            'children' => ['user'],
        ],
    ],
];
