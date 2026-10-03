<?php
/**
 * app.php — Returns the merged app configuration as a structured array.
 *
 * Single source of truth for runtime config. Reads .env values via env().
 * Other code should access config through config('key.path') (defined in
 * bootstrap/helpers.php) rather than reading $_ENV directly.
 */

return [

    // ── App identity ────────────────────────────────────
    'name'         => env('APP_NAME', 'FPC'),
    'env'          => env('APP_ENV', 'production'),
    'debug'        => env('APP_DEBUG', false),
    'url'          => env('APP_URL', 'http://localhost'),
    'timezone'     => env('APP_TIMEZONE', 'UTC'),
    'key'          => env('APP_KEY'),

    // ── Client identity (which deployment is this) ──────
    'client' => [
        'slug'      => env('CLIENT_SLUG', 'default'),
        'name'      => env('CLIENT_NAME', 'Default Client'),
        'full_name' => env('CLIENT_FULL_NAME', ''),
        'country'   => env('CLIENT_COUNTRY', 'NG'),
        'currency'  => env('CLIENT_CURRENCY', 'NGN'),
        'locale'    => env('CLIENT_LOCALE', 'en_NG'),
    ],

    // ── Sessions ────────────────────────────────────────
    'session' => [
        'lifetime'      => (int) env('SESSION_LIFETIME', 120),
        'secure_cookie' => env('SESSION_SECURE_COOKIE', true),
        'name'          => 'fpc_session',
    ],

    // ── CSRF ────────────────────────────────────────────
    'csrf' => [
        'lifetime' => (int) env('CSRF_TOKEN_LIFETIME', 3600),
    ],

    // ── Storage ─────────────────────────────────────────
    'storage' => [
        'backend'        => env('STORAGE_BACKEND', 'local'),
        'path'           => env('STORAGE_PATH', 'storage/uploads'),
        'max_upload_mb'  => (int) env('STORAGE_MAX_UPLOAD_MB', 20),
        // S3-compatible object storage. Used for Cloudflare R2, which is
        // S3-compatible but needs an explicit endpoint and region 'auto'.
        // Set STORAGE_BACKEND=s3 to route new evidence uploads here.
        's3' => [
            'bucket'   => env('S3_BUCKET'),
            'region'   => env('S3_REGION', 'auto'),
            'key'      => env('S3_KEY'),
            'secret'   => env('S3_SECRET'),
            // R2: https://<account_id>.r2.cloudflarestorage.com
            // Without this the client would address AWS, not R2.
            'endpoint' => env('S3_ENDPOINT'),
            // Prefixed onto every key, so one bucket can hold several clients.
            'prefix'   => env('S3_PREFIX', 'fpc'),
            // Seconds a presigned download link stays valid. Deliberately
            // short: a leaked link works until it expires, and some evidence
            // is GBV-related (ea_evidence.is_restricted).
            'url_ttl'  => (int) env('S3_URL_TTL', 300),
        ],
    ],

    // ── Logging ─────────────────────────────────────────
    'log' => [
        'level'          => env('LOG_LEVEL', 'info'),
        'channel'        => env('LOG_CHANNEL', 'file'),
        'path'           => env('LOG_PATH', 'storage/logs'),
        'retention_days' => (int) env('LOG_RETENTION_DAYS', 90),
    ],

    // ── Audit ───────────────────────────────────────────
    'audit' => [
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 2555),
    ],

    // ── Rate limits ─────────────────────────────────────
    'rate_limit' => [
        'login_attempts'    => (int) env('RATE_LIMIT_LOGIN', 5),
        'lockout_minutes'   => (int) env('RATE_LIMIT_LOCKOUT_MINUTES', 15),
        'api_per_minute'    => (int) env('RATE_LIMIT_API_PER_MINUTE', 60),
    ],
];
