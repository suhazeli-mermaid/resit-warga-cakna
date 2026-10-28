<?php

return [
    'enabled' => env('UPDATE_MANAGER_ENABLED', false),
    'remote' => env('UPDATE_MANAGER_REMOTE', 'origin'),
    'branch' => env('UPDATE_MANAGER_BRANCH', 'main'),
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
        'mysqldump_binary' => env('UPDATE_MYSQLDUMP_BINARY', '/usr/bin/mysqldump'),
    ],
];
