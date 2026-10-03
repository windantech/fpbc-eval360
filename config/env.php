<?php
/**
 * env.php — Loads .env files into $_ENV / $_SERVER / getenv().
 *
 * Lightweight (no Composer dependency) so it works on shared cPanel
 * hosting where some deployments don't have Composer.
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   env_load(__DIR__ . '/../.env');
 *   $dbHost = env('DB_HOST', 'localhost');  // value or default
 */

if (!function_exists('env_load')) {
    /**
     * Load a .env file. Values become available via env() and getenv().
     */
    function env_load(string $path): void
    {
        if (!is_readable($path)) {
            // Silent in production; throw in development
            if ((getenv('APP_ENV') ?: 'production') === 'development') {
                throw new RuntimeException("env file not readable: $path");
            }
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Strip surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            // Resolve ${VAR} references to previously-loaded values
            $value = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function ($m) {
                return getenv($m[1]) !== false ? getenv($m[1]) : '';
            }, $value);
            putenv("$key=$value");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Get an env var, with type coercion for booleans and a default.
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') return $default;
        switch (strtolower($value)) {
            case 'true':  case '(true)':  return true;
            case 'false': case '(false)': return false;
            case 'null':  case '(null)':  return null;
            case 'empty': case '(empty)': return '';
        }
        return $value;
    }
}
