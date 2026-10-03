<?php
/**
 * helpers.php — Global helper functions.
 *
 * Loaded after env, before everything else. Provides:
 *   - config('key.path', $default)
 *   - feature('flag_name')
 *   - app_path(...$parts)
 *   - storage_path(...$parts)
 *   - logger($channel)
 *   - csrf_token() / csrf_check()
 *   - e() / clean()
 */

if (!function_exists('config')) {
    /**
     * Read a dotted config path, e.g. config('app.session.lifetime').
     * Config files live in /config/ and are loaded lazily.
     */
    function config(string $path, $default = null)
    {
        static $cache = [];
        $parts = explode('.', $path);
        $file  = array_shift($parts);
        if (!isset($cache[$file])) {
            $configFile = APP_ROOT . "/config/{$file}.php";
            $cache[$file] = file_exists($configFile) ? require $configFile : [];
        }
        $value = $cache[$file];
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }
}

if (!function_exists('feature')) {
    /**
     * Check whether a feature flag is enabled.
     */
    function feature(string $flag): bool
    {
        return (bool) config("features.$flag", false);
    }
}

if (!function_exists('app_path')) {
    function app_path(string ...$parts): string
    {
        return APP_ROOT . (empty($parts) ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string ...$parts): string
    {
        return APP_ROOT . DIRECTORY_SEPARATOR . 'storage'
            . (empty($parts) ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML. Shortcut for htmlspecialchars with sensible defaults.
     */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('clean')) {
    /**
     * Trim + strip control chars from user input.
     */
    function clean($value): string
    {
        if (!is_scalar($value)) return '';
        $s = trim((string) $value);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Issue (or fetch) the current session CSRF token.
     */
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token']) || ($_SESSION['_csrf_issued_at'] ?? 0) < time() - (int) config('app.csrf.lifetime', 3600)) {
            $_SESSION['_csrf_token']     = bin2hex(random_bytes(32));
            $_SESSION['_csrf_issued_at'] = time();
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    /**
     * Validate a posted CSRF token. Returns bool.
     */
    function csrf_check(?string $posted): bool
    {
        if (!$posted) return false;
        $session = $_SESSION['_csrf_token'] ?? '';
        return $session !== '' && hash_equals($session, $posted);
    }
}

if (!function_exists('logger')) {
    /**
     * Tiny logger. Writes to storage/logs/<channel>.log.
     * For full logging, swap in Monolog later — this is the seam.
     */
    function logger(string $message, string $level = 'info', string $channel = 'app'): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $line = sprintf("[%s] %s.%s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $channel, $message);
        // Wrap in try/catch because the set_error_handler converts PHP warnings to
        // exceptions, and a failed write must not recurse back into logger().
        try {
            file_put_contents($dir . DIRECTORY_SEPARATOR . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Cannot write log — fail silently to prevent recursion
        }
    }
}

if (!function_exists('json_response')) {
    /**
     * Send a JSON response and exit.
     */
    function json_response($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): void
    {
        header("Location: $url", true, $status);
        exit;
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('require_login')) {
    function require_login(string $loginUrl = '/login.php'): void
    {
        if (!current_user_id()) {
            redirect($loginUrl);
        }
    }
}

if (!function_exists('app_url')) {
    /**
     * Build an absolute URL respecting APP_URL.
     * Handles subpath deployments like https://example.com/fpc/public/ correctly.
     *
     * @param string $path  Path relative to web root, with or without leading slash.
     *                      Will be appended after APP_URL.
     *                      Examples:
     *                        app_url()                              → https://mel.fpc.tech/fpc
     *                        app_url('/login.php')                  → https://mel.fpc.tech/fpc/login.php
     *                        app_url('/modules/ea/pages/foo.php')   → https://mel.fpc.tech/fpc/modules/ea/pages/foo.php
     */
    function app_url(string $path = ''): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($path === '') return $base;
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('public_url')) {
    /**
     * Build an absolute URL pointing into the public/ directory.
     * Useful for assets and login/logout pages which live under public/.
     *
     * Example:
     *   public_url('/login.php')   → https://mel.fpc.tech/fpc/public/login.php
     */
    function public_url(string $path = ''): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($path === '') return $base . '/public';
        $path = '/' . ltrim($path, '/');
        return $base . '/public' . $path;
    }
}
