<?php

// Fixture data for ClaudeRunCleanupServiceTest
// User A = id 100 (admin), User B = id 1 (userWithField)
// Project 1 = id 1 (owned by user 100)

$now = date('Y-m-d H:i:s');

return [
    // Session 1: 3 completed runs (user A)
    'session1_run1' => [
        'id' => 1,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => 'session-aaa',
        'status' => 'completed',
        'prompt_markdown' => 'Test prompt 1',
        'prompt_summary' => 'Test 1',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    'session1_run2' => [
        'id' => 2,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => 'session-aaa',
        'status' => 'completed',
        'prompt_markdown' => 'Test prompt 2',
        'prompt_summary' => 'Test 2',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    'session1_run3' => [
        'id' => 3,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => 'session-aaa',
        'status' => 'completed',
        'prompt_markdown' => 'Test prompt 3',
        'prompt_summary' => 'Test 3',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],

    // Standalone failed run (user A, no session_id)
    'standalone_failed' => [
        'id' => 4,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => null,
        'status' => 'failed',
        'prompt_markdown' => 'Failed prompt',
        'prompt_summary' => 'Failed',
        'error_message' => 'Something went wrong',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],

    // Mixed session: 1 completed + 1 running (user A)
    'mixed_completed' => [
        'id' => 5,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => 'session-bbb',
        'status' => 'completed',
        'prompt_markdown' => 'Mixed completed',
        'prompt_summary' => 'Mixed completed',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    'mixed_running' => [
        'id' => 6,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => 'session-bbb',
        'status' => 'running',
        'prompt_markdown' => 'Mixed running',
        'prompt_summary' => 'Mixed running',
        'started_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],

    // Standalone pending run (user A)
    'standalone_pending' => [
        'id' => 7,
        'user_id' => 100,
        'project_id' => 1,
        'session_id' => null,
        'status' => 'pending',
        'prompt_markdown' => 'Pending prompt',
        'prompt_summary' => 'Pending',
        'created_at' => $now,
        'updated_at' => $now,
    ],

    // Completed run owned by User B (for ownership tests)
    'other_user_run' => [
        'id' => 8,
        'user_id' => 1,
        'project_id' => 2,
        'session_id' => 'session-ccc',
        'status' => 'completed',
        'prompt_markdown' => 'Other user prompt',
        'prompt_summary' => 'Other user',
        'started_at' => $now,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
];
