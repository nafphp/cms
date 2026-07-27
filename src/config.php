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
        'trust_proxy_headers' => false,
        'trusted_proxies' => [],
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
    'ai' => [
        'enabled' => '0',
        'type' => 'local',
        'url' => 'http://localhost:11434',
        'model' => 'llama3.1:8b',
        'embedding_model' => '',
        'routing_debug' => false,
        'instructions' => '',
    ],
    'view' => [
        'paths' => [
            'views',
            'app/views',
        ],
        'assets' => [
            'publicPath' => BASE_PATH . '/public',
            'versioning' => true,
            'versionParameter' => 'v',
        ],
    ],
    'cms' => [
        'runtime_path' => 'storage/nixcms',
        'languages' => ['en', 'de'],
        'outbound_http' => [
            'webhooks' => [
                'allow_development_http' => false,
                'development_hosts' => [],
                'timeout' => 8.0,
                'max_response_bytes' => 65536,
            ],
            'ollama' => [
                'allowed_ports' => [11434],
                'max_response_bytes' => 1048576,
            ],
        ],
        'admin' => [
            'accent_colors' => [
                'dashboard' => '#ff3b30',
                'content' => '#3f7fbd',
                'articles' => '#3f7fbd',
                'media' => '#14b8a6',
                'users' => '#0ea5e9',
                'roles' => '#f59e0b',
                'settings' => '#8b5cf6',
            ],
        ],
        'media' => [
            'storage_path' => BASE_PATH . '/storage/media',
            'public_url' => '/media',
            'max_upload_size' => 25 * 1024 * 1024,
        ],
    ],
];
