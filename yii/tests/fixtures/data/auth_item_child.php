<?php
return [
    ['parent' => 'user', 'child' => 'createField'],
    ['parent' => 'user', 'child' => 'viewField'],
    ['parent' => 'user', 'child' => 'updateField'],
    ['parent' => 'user', 'child' => 'deleteField'],

    ['parent' => 'user', 'child' => 'createProject'],
    ['parent' => 'user', 'child' => 'viewProject'],
    ['parent' => 'user', 'child' => 'updateProject'],
    ['parent' => 'user', 'child' => 'deleteProject'],
    ['parent' => 'user', 'child' => 'setCurrentProject'],

    ['parent' => 'user', 'child' => 'createContext'],
    ['parent' => 'user', 'child' => 'viewContext'],
    ['parent' => 'user', 'child' => 'updateContext'],
    ['parent' => 'user', 'child' => 'deleteContext'],

    ['parent' => 'user', 'child' => 'createPromptTemplate'],
    ['parent' => 'user', 'child' => 'viewPromptTemplate'],
    ['parent' => 'user', 'child' => 'updatePromptTemplate'],
    ['parent' => 'user', 'child' => 'deletePromptTemplate'],

    ['parent' => 'admin', 'child' => 'user'],
];
