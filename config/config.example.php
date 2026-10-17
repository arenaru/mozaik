<?php
/**
 * Application configuration.
 *
 * Values come from environment variables (set by docker-compose from .env).
 * To override locally without Docker, copy this file to config/config.php
 * (git-ignored) and hardcode values there. Never commit real credentials.
 */

declare(strict_types=1);

$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

return [
    'app' => [
        'name' => 'Mozaic',
        'env'  => $env('APP_ENV', 'production'),
        'url'  => rtrim($env('APP_URL', 'http://localhost:8080'), '/'),
    ],

    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'mozaic'),
        'user' => $env('DB_USER', 'mozaic_app'),
        'pass' => $env('DB_PASS', ''),
    ],

    'session' => [
        'name'          => 'mozaic_sid',
        // Must be true in production (HTTPS). False only for http://localhost.
        'cookie_secure' => $env('COOKIE_SECURE', '1') === '1',
        'idle_timeout'  => 7200, // seconds
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],

    'upload' => [
        'dir'          => dirname(__DIR__) . '/public/uploads',
        'url_prefix'   => '/uploads',
        'max_bytes'    => 5 * 1024 * 1024,
        'max_width'    => 8000,
        'max_height'   => 8000,
        'max_pixels'   => 40_000_000, // guards against decompression bombs
    ],
];
