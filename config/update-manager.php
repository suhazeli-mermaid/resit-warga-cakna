<?php

return [
    'enabled' => env('UPDATE_MANAGER_ENABLED', false),
    'remote' => env('UPDATE_MANAGER_REMOTE', 'origin'),
    'branch' => env('UPDATE_MANAGER_BRANCH', 'main'),
    'deployment_mode' => 'terminal',
    'repository_path' => base_path(),
    'shell_private_path' => storage_path('app/private'),
    'shell_history_path' => storage_path('app/private/update-deployments/history'),
    'shell_runtime_path' => storage_path('app/private/update-deployments/runtime'),
    'shell_command' => 'bash deploy.sh',
    'backup_disk' => 'local',
    'minimum_free_bytes' => 250 * 1024 * 1024,
    'critical_routes' => ['public.index', 'login', 'receipts.index', 'vouchers.index'],
    'protected_patterns' => [
        '.env',
        '.env.*',
        'auth.json',
        'storage/**',
        'public/storage/**',
        '**/*credential*',
        '**/*secret*',
        '**/*private-key*',
        '**/*payment-proof*',
        '**/uploads/**',
    ],
    'database_backup' => [
        'enabled' => env('UPDATE_DATABASE_BACKUP_ENABLED', false),
        'connection' => null,
        'mysqldump_binary' => env('UPDATE_MYSQLDUMP_BINARY', '/usr/bin/mysqldump'),
        'no_tablespaces' => env('UPDATE_MYSQLDUMP_NO_TABLESPACES', false),
    ],
];
