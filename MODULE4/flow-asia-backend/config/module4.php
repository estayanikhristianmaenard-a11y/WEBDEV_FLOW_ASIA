<?php

use FlowAsia\Module4\Services\DatabaseCompanyEventSource;
use FlowAsia\Module4\Services\SessionCalendarIdentitySource;
use FlowAsia\Module4\Services\StubCalendarContextSource;
use FlowAsia\Module4\Services\StubCalendarDataSource;
use FlowAsia\Module4\Services\StubCalendarEmployeeDirectory;

$shared = env('M4_MODE', 'standalone') === 'shared';

return [
    'mode' => env('M4_MODE', 'standalone'),
    'timezone' => 'Asia/Manila',
    'route_prefix' => env('M4_ROUTE_PREFIX', $shared ? 'modules/m4' : ''),
    'route_name_prefix' => env('M4_ROUTE_NAME_PREFIX', $shared ? 'm4.' : ''),
    'auth_middleware' => env('M4_AUTH_MIDDLEWARE', 'auth'),
    'frontend_path' => env('M4_FRONTEND_PATH', dirname(__DIR__, 2).'/flow-asia-dashboard-prototype'),
    'standalone_preview' => env('M4_STANDALONE_PREVIEW', false),
    'preview_user_email' => env('M4_PREVIEW_USER_EMAIL', 'hr@test.com'),
    'sources' => [
        'attendance' => env('M4_ATTENDANCE_SOURCE', $shared ? null : StubCalendarDataSource::class),
        'context' => env('M4_CONTEXT_SOURCE', $shared ? null : StubCalendarContextSource::class),
        'employees' => env('M4_EMPLOYEE_DIRECTORY', $shared ? null : StubCalendarEmployeeDirectory::class),
        'events' => env('M4_EVENT_SOURCE', $shared ? null : DatabaseCompanyEventSource::class),
        'identity' => env('M4_IDENTITY_SOURCE', SessionCalendarIdentitySource::class),
    ],
    'identity' => [
        'fields' => ['name' => 'name', 'role' => 'role', 'employee_id' => 'employee_id'],
        'roles' => [],
    ],
];
