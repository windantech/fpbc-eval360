<?php
/**
 * query.php — Thin query helpers built on PDO.
 *
 * Use these instead of raw $pdo->prepare()/execute() for one-line readability.
 * For complex queries, drop down to db()->prepare(...) directly.
 *
 * All helpers use prepared statements; never interpolate user input.
 */

if (!function_exists('db_assert_identifier')) {
    /**
     * Guard against SQL identifier injection in table/column names.
     * Identifiers must be alphanumeric + underscores only.
     * These come from internal code, not user input, but we validate defensively.
     */
    function db_assert_identifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid SQL identifier: '$name'");
        }
    }
}

if (!function_exists('db_one')) {
    /**
     * Fetch one row or null.
     *   $row = db_one('SELECT * FROM users WHERE id = ?', [$id]);
     */
    function db_one(string $sql, array $params = []): ?array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

if (!function_exists('db_all')) {
    /**
     * Fetch all rows.
     *   $rows = db_all('SELECT * FROM ea_grm_register WHERE period_id = ?', [$pid]);
     */
    function db_all(string $sql, array $params = []): array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('db_value')) {
    /**
     * Fetch a single scalar value (first column of first row) or null.
     *   $count = db_value('SELECT COUNT(*) FROM users');
     */
    function db_value(string $sql, array $params = [])
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }
}

if (!function_exists('db_column')) {
    /**
     * Fetch a single column as an array.
     *   $ids = db_column('SELECT id FROM ea_activities WHERE period_id = ?', [$pid]);
     */
    function db_column(string $sql, array $params = []): array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('db_insert')) {
    /**
     * Insert an associative array into a table, return new id.
     *   $id = db_insert('ea_grm_register', ['period_id' => 4, 'summary' => '...']);
     *
     * IMPORTANT: org_id is added automatically if not present (defaults to 1).
     */
    function db_insert(string $table, array $data): int
    {
        db_assert_identifier($table);
        if (!array_key_exists('org_id', $data)) {
            $data['org_id'] = 1;
        }
        $cols   = array_keys($data);
        foreach ($cols as $c) db_assert_identifier($c);
        $place  = array_map(fn($c) => ':' . $c, $cols);
        $sql    = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $place)
        );
        $stmt = db()->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
        }
        $stmt->execute();
        return (int) db()->lastInsertId();
    }
}

if (!function_exists('db_update')) {
    /**
     * Update rows where conditions match.
     *   db_update('ea_grm_register', ['status' => 'resolved'], ['id' => $id]);
     */
    function db_update(string $table, array $data, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('db_update() requires a WHERE clause for safety');
        }
        db_assert_identifier($table);
        foreach (array_keys($data)  as $c) db_assert_identifier($c);
        foreach (array_keys($where) as $c) db_assert_identifier($c);
        $set = implode(', ', array_map(fn($c) => "`$c` = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($c) => "`$c` = :where_$c", array_keys($where)));
        $sql = "UPDATE `$table` SET $set WHERE $cond";
        $stmt = db()->prepare($sql);
        foreach ($data  as $k => $v) $stmt->bindValue(":set_$k",   $v, is_null($v) ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
        foreach ($where as $k => $v) $stmt->bindValue(":where_$k", $v, is_null($v) ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
        $stmt->execute();
        return $stmt->rowCount();
    }
}

if (!function_exists('db_delete')) {
    /**
     * Delete rows where conditions match.
     */
    function db_delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('db_delete() requires a WHERE clause for safety');
        }
        db_assert_identifier($table);
        foreach (array_keys($where) as $c) db_assert_identifier($c);
        $cond = implode(' AND ', array_map(fn($c) => "`$c` = :$c", array_keys($where)));
        $stmt = db()->prepare("DELETE FROM `$table` WHERE $cond");
        $stmt->execute($where);
        return $stmt->rowCount();
    }
}

if (!function_exists('db_exists')) {
    /**
     * Quick existence check.
     *   db_exists('users', ['email' => $email])
     */
    function db_exists(string $table, array $where): bool
    {
        db_assert_identifier($table);
        foreach (array_keys($where) as $c) db_assert_identifier($c);
        $cond = implode(' AND ', array_map(fn($c) => "`$c` = :$c", array_keys($where)));
        return (bool) db_value("SELECT 1 FROM `$table` WHERE $cond LIMIT 1", $where);
    }
}
