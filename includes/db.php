<?php

require_once __DIR__ . '/../config/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Public-facing contest: the one marked is_active = 1.
 * A contest in 'setup' status with is_active = 0 is never shown publicly.
 */
function resolvePublicContest(): array|false
{
    return getDB()->query("SELECT * FROM contests WHERE is_active = 1 LIMIT 1")->fetch();
}

/**
 * Admin contest: respects ?contest_id= URL param; falls back to most recent by id.
 */
function resolveAdminContest(): array|false
{
    $id = (int) ($_GET['contest_id'] ?? 0);
    if ($id > 0) {
        $stmt = getDB()->prepare('SELECT * FROM contests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    return getDB()->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
}
