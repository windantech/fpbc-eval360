<?php
/**
 * init.php — Application bootstrap.
 *
 * Every entry point (page, AJAX endpoint, webhook receiver) should
 * `require_once __DIR__ . '/path/to/bootstrap/init.php';` as its first line.
 *
 * Initialisation order:
 *   1. Define APP_ROOT
 *   2. Load env file
 *   3. Set timezone + error reporting
 *   4. Load helpers (config(), feature(), etc.)
 *   5. Initialise DB connections
 *   6. Start session
 *   7. Set up error handler
 */

declare(strict_types=1);

// 1. Root path (the project root)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 2. Load env loader and .env file
require_once APP_ROOT . '/config/env.php';
env_load(APP_ROOT . '/.env');
// Machine-local overrides (git-ignored). Loaded AFTER .env so local values win
// — lets a dev box run against a local DB / http without editing the shared,
// production-valued .env. Absent in production, so nothing changes there.
env_load(APP_ROOT . '/.env.local');

// 3. Timezone + error reporting based on environment
date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

$isDebug = env('APP_DEBUG', false);
if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php_errors.log');
}

// 4. Helpers (config, feature flags, etc.)
require_once APP_ROOT . '/bootstrap/helpers.php';

// 5. DB connections (PDO + MySQLi, both available)
require_once APP_ROOT . '/db/connection.php';
require_once APP_ROOT . '/db/query.php';
db_connect();

// 6. Session
if (session_status() === PHP_SESSION_NONE) {
    $secure    = env('SESSION_SECURE_COOKIE', true);

    // Session storage location is per-deployment, so it lives in .env — never
    // hardcode a server path here (see README: deployments differ via .env).
    // cPanel hosts often point session.save_path at a directory that does not
    // exist (e.g. /var/cpanel/php/sessions/ea-php82), which makes session_start()
    // fail; set SESSION_SAVE_PATH to a writable dir OUTSIDE the web root.
    // Leave it unset to use PHP's own default (fine for local XAMPP dev).
    $sessPath = (string) env('SESSION_SAVE_PATH', '');

    if ($sessPath !== '') {
        // Create the directory if it does not exist (private perms).
        if (!is_dir($sessPath)) {
            @mkdir($sessPath, 0700, true);
        }
        if (!is_dir($sessPath) || !is_writable($sessPath)) {
            throw new RuntimeException("Session save path is not writable: {$sessPath}");
        }
        session_save_path($sessPath);
    }

    session_name(config('app.session.name', 'fpc_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (bool) $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 7. Error handler — catches anything uncaught and logs it nicely
set_exception_handler(function (Throwable $e) {
    $msg = sprintf(
        "[%s] %s in %s:%d\n%s",
        date('Y-m-d H:i:s'),
        get_class($e) . ': ' . $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($msg);
    if (env('APP_DEBUG', false)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
    } else {
        http_response_code(500);
        echo 'An unexpected error occurred. Please try again or contact support.';
    }
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
