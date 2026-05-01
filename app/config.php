<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'NixCMS',
        'short_name' => 'NX',
        'env' => 'ENV:APP_ENV',
        'url' => 'ENV:APP_URL',
    ],
    'language' => 'en',
    'fallback_language' => 'en',
    'database' => [
        'driver' => 'ENV:DB_DRIVER',
        'host' => 'ENV:DB_HOST',
        'port' => 'ENV:DB_PORT',
        'username' => 'ENV:DB_USERNAME',
        'password' => 'ENV:DB_PASSWORD',
        'database' => 'ENV:DB_DATABASE',
    ],
    'session' => [
        'storage' => 'default',
        'database_table' => 'sessions',
    ],
    'schedule' => [
        'coalesce' => true,
    ],
    'mcp' => [
        'auth' => [
            'enabled' => true,
            'driver' => 'file',
            'token_file' => BASE_PATH . '/storage/mcp/tokens.json',
        ],
    ],
    'view' => [
        'paths' => [
            'views',
            'app/views',
        ],
    ],
    'cms' => [
        'languages' => ['en', 'de'],
        'auth_enabled' => true,
        'setup_mode' => true,
        'admin' => [
            'accent_colors' => [
                'dashboard' => '#ff3b30',
                'content' => '#3f7fbd',
                'articles' => '#3f7fbd',
                'users' => '#0ea5e9',
                'roles' => '#f59e0b',
                'settings' => '#8b5cf6',
            ],
        ],
    ],
];
