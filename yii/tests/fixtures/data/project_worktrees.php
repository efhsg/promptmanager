<?php

return [
    'worktree1' => [
        'id' => 1,
        'project_id' => 1,
        'purpose' => 'feature',
        'branch' => 'feature/auth',
        'path_suffix' => 'auth',
        'source_branch' => 'main',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
    'worktree2' => [
        'id' => 2,
        'project_id' => 1,
        'purpose' => 'bugfix',
        'branch' => 'bugfix/login-error',
        'path_suffix' => 'bugfix',
        'source_branch' => 'main',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
    'worktree3' => [
        'id' => 3,
        'project_id' => 2,
        'purpose' => 'refactor',
        'branch' => 'refactor/cleanup',
        'path_suffix' => 'refactor1',
        'source_branch' => 'main',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
];
