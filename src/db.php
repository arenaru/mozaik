<?php
/**
 * PDO connection (singleton).
 *
 * Rules:
 *  - ALWAYS use prepared statements with bound parameters.
 *  - Never concatenate user input into SQL. For ORDER BY / column names,
 *    pick from a hardcoded whitelist.
 *  - Bind LIMIT/OFFSET as PDO::PARAM_INT.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        config('db.host'),
        config('db.port'),
        config('db.name')
    );

    try {
        $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real server-side prepares
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Log details server-side only; the user sees a generic error page.
        error_log('[mozaic] DB connection failed: ' . $e->getMessage());
        abort(500);
    }

    return $pdo;
}

/**
 * Run a prepared statement and return it.
 *
 *     $rows = db_query('SELECT * FROM pins WHERE user_id = ?', [$id])->fetchAll();
 *
 * Integers are bound as PARAM_INT (needed for LIMIT/OFFSET).
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $i = 1;
    foreach ($params as $key => $value) {
        $name = is_int($key) ? $i++ : ':' . ltrim((string) $key, ':');
        $type = match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
        $stmt->bindValue($name, $value, $type);
    }
    $stmt->execute();
    return $stmt;
}
