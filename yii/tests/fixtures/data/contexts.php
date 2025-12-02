<?php

return [
    [
        'id' => 1,
        'project_id' => 1,
        'name' => 'Test Context',
        'content' => 'This is a test context',
        'created_at' => time(),
        'updated_at' => time(),
    ],
    [
        'id' => 2,
        'project_id' => 2,
        'name' => 'Test Context2',
        'content' => 'This is a test context',
        'created_at' => time(),
        'updated_at' => time(),
    ],
    [
        'id' => 3,
        'project_id' => 1,
        'name' => 'Test Context3',
        'content' => 'This is a second test context',
        'created_at' => time(),
        'updated_at' => time(),
    ],
    [
        'id' => 4,
        'project_id' => 3,
        'name' => 'Shared Linked Context',
        'content' => 'Shared context from a linked project',
        'is_default' => 1,
        'share' => 1,
        'created_at' => time(),
        'updated_at' => time(),
    ],
    [
        'id' => 5,
        'project_id' => 3,
        'name' => 'Private Linked Context',
        'content' => 'Private context from a linked project',
        'is_default' => 1,
        'share' => 0,
        'created_at' => time(),
        'updated_at' => time(),
    ],
];
