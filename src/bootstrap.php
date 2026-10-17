<?php
/**
 * Every public page starts with:
 *     require __DIR__ . '/../src/bootstrap.php';
 *
 * Loads config, hardens error handling, starts a secure session,
 * sends security headers and loads the helper libraries.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// --- Config: config/config.php (local override) or the env-driven example ---
$configFile = is_file(APP_ROOT . '/config/config.php')
    ? APP_ROOT . '/config/config.php'
    : APP_ROOT . '/config/config.example.php';
$GLOBALS['config'] = require $configFile;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

// --- Error handling: log everything, show nothing ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/csrf.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/validation.php';
require APP_ROOT . '/src/upload.php';
require APP_ROOT . '/src/audit.php';

set_exception_handler(static function (Throwable $e): void {
    error_log('[mozaic] Uncaught ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    abort(500);
});

if (PHP_SAPI === 'cli') {
    return; // CLI scripts need config + DB, not sessions/headers.
}

// --- Security headers ---
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
    . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
    . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
if (config('session.cookie_secure')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// --- Session ---
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name(config('session.name', 'mozaic_sid'));
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (bool) config('session.cookie_secure', true),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Idle timeout: drop the session after inactivity.
$now = time();
$idle = (int) config('session.idle_timeout', 7200);
if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > $idle) {
    session_unset();
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = $now;
