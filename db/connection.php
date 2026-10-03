<?php
/**
 * connection.php — DB connection bootstrap.
 *
 * FPC historically uses MySQLi for legacy modules and PDO for new code.
 * We expose both so the new E&S Compliance module can use PDO (cleaner,
 * exception-based) while existing FPC code continues using MySQLi.
 *
 * Globals exposed (matching FPC convention):
 *   $conn   — MySQLi connection (legacy compatibility)
 *   $pdo    — PDO connection (preferred for new code)
 *
 * Helper functions:
 *   db()      — returns the PDO instance
 *   db_mysqli() — returns the MySQLi instance
 *   db_connect() — establishes both connections (idempotent)
 */

if (!function_exists('db_connect')) {

    function db_connect(): void
    {
        global $conn, $pdo;
        if (isset($pdo) && $pdo instanceof PDO && isset($conn) && $conn instanceof mysqli) return;

        $cfg = config('database.connections.primary');
        if (!$cfg) {
            throw new RuntimeException('Database config missing — check config/database.php');
        }

        // ── PDO ─────────────────────────────────────────
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], (int) $cfg['port'], $cfg['database'], $cfg['charset']
        );
        try {
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);
            // Set session timezone to match app timezone (so NOW() etc. align)
            $tz = date('P'); // e.g. +01:00
            $pdo->exec("SET time_zone = '$tz'");
        } catch (PDOException $e) {
            // Don't leak credentials into logs
            logger("PDO connection failed: " . $e->getMessage(), 'error', 'db');
            throw new RuntimeException('Database unavailable. Check connection settings.');
        }

        // ── MySQLi (legacy) ─────────────────────────────
        // MYSQLI_REPORT_OFF prevents automatic exceptions so we can check connect_error
        // ourselves and give a clean error message (without leaking credentials).
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = new mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database'], (int) $cfg['port']);
        if ($conn->connect_error) {
            logger("MySQLi connection failed: " . $conn->connect_error, 'error', 'db');
            throw new RuntimeException('Database unavailable (mysqli).');
        }
        $conn->set_charset($cfg['charset']);
    }

    function db(): PDO
    {
        global $pdo;
        if (!isset($pdo)) db_connect();
        return $pdo;
    }

    function db_mysqli(): mysqli
    {
        global $conn;
        if (!isset($conn)) db_connect();
        return $conn;
    }

    /**
     * Wrap a callable in a transaction (PDO).
     * Rolls back on exception, commits otherwise.
     */
    function db_transaction(callable $fn)
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
