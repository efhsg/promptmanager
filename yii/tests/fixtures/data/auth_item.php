<?php
return [
    [
        'name' => 'createField',
        'type' => 2,
        'description' => 'Create a Field',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewField',
        'type' => 2,
        'description' => 'View a Field',
        'rule_name' => 'isfieldOwner',
        'data' => null,
    ],
    [
        'name' => 'updateField',
        'type' => 2,
        'description' => 'Update a Field',
        'rule_name' => 'isfieldOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteField',
        'type' => 2,
        'description' => 'Delete a Field',
        'rule_name' => 'isfieldOwner',
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
        'rule_name' => 'isProjectOwner',
        'data' => null,
    ],
    [
        'name' => 'updateProject',
        'type' => 2,
        'description' => 'Update a Project',
        'rule_name' => 'isProjectOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteProject',
        'type' => 2,
        'description' => 'Delete a Project',
        'rule_name' => 'isProjectOwner',
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
        'rule_name' => 'isContextOwner',
        'data' => null,
    ],
    [
        'name' => 'updateContext',
        'type' => 2,
        'description' => 'Update a Context',
        'rule_name' => 'isContextOwner',
        'data' => null,
    ],
    [
        'name' => 'deleteContext',
        'type' => 2,
        'description' => 'Delete a Context',
        'rule_name' => 'isContextOwner',
        'data' => null,
    ],
    [
        'name' => 'createPromptTemplate',
        'type' => 2,
        'description' => 'Create a Prompt Template',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewPromptTemplate',
        'type' => 2,
        'description' => 'View a Prompt Template',
        'rule_name' => 'isPromptTemplateOwner',
        'data' => null,
    ],
    [
        'name' => 'updatePromptTemplate',
        'type' => 2,
        'description' => 'Update a Prompt Template',
        'rule_name' => 'isPromptTemplateOwner',
        'data' => null,
    ],
    [
        'name' => 'deletePromptTemplate',
        'type' => 2,
        'description' => 'Delete a Prompt Template',
        'rule_name' => 'isPromptTemplateOwner',
        'data' => null,
    ],
    [
        'name' => 'createPromptInstance',
        'type' => 2,
        'description' => 'Create a Prompt Instance',
        'rule_name' => null,
        'data' => null,
    ],
    [
        'name' => 'viewPromptInstance',
        'type' => 2,
        'description' => 'View a Prompt Instance',
        'rule_name' => 'isPromptInstanceOwner',
        'data' => null,
    ],
    [
        'name' => 'updatePromptInstance',
        'type' => 2,
        'description' => 'Update a Prompt Instance',
        'rule_name' => 'isPromptInstanceOwner',
        'data' => null,
    ],
    [
        'name' => 'deletePromptInstance',
        'type' => 2,
        'description' => 'Delete a Prompt Instance',
        'rule_name' => 'isPromptInstanceOwner',
        'data' => null,
    ],
    [
        'name' => 'generatePromptForm',
        'type' => 2,
        'description' => 'Generate a Prompt Instance form',
        'rule_name' => 'isPromptTemplateOwner',
        'data' => null,
    ],
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
