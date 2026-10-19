<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Task files, comment files, proof of work and suggestion references. Files are never served
    | straight from the disk: AttachmentController checks the viewer may open the parent record.
    |
    */

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'max_kb' => 10240,
        'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'],
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_per_upload' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subtasks
    |--------------------------------------------------------------------------
    |
    | depth 0 = a task, 1 = a subtask, 2 = a sub-subtask (numbered 1, 1.2, 1.2.3).
    |
    */

    'max_task_depth' => 2,

    /*
    |--------------------------------------------------------------------------
    | Currently working
    |--------------------------------------------------------------------------
    */

    'working_session_hours' => 8,

    /*
    |--------------------------------------------------------------------------
    | Inline deadline checks
    |--------------------------------------------------------------------------
    |
    | Sends deadline reminders and refreshes project health after a signed-in request, at most every
    | 5 minutes, so the app stays current on hosts where the scheduler cron is not configured.
    |
    */

    'inline_deadline_checks' => (bool) env('INLINE_DEADLINE_CHECKS', true),

    'admin' => [
        'name' => env('ADMIN_NAME', 'System Administrator'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
