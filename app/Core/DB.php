<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** لایه‌ی دسترسی به دیتابیس: PDO با prepared statement واقعی و bind نوع‌دار. */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) env('DB_HOST', 'localhost'),
                (int) env('DB_PORT', 3306),
                (string) env('DB_NAME', 'taskam')
            );
            $tz = (string) env('DB_TIMEZONE', '+03:30');
            $pdo = new PDO($dsn, (string) env('DB_USER', ''), (string) env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '" . preg_replace('/[^0-9+\-:]/', '', $tz) . "'");
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    private static function stmt(string $sql, array $params): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $v) {
            if (is_bool($v)) {
                $st->bindValue($i++, (int) $v, PDO::PARAM_INT);
            } elseif (is_int($v)) {
                $st->bindValue($i++, $v, PDO::PARAM_INT);
            } elseif ($v === null) {
                $st->bindValue($i++, null, PDO::PARAM_NULL);
            } else {
                $st->bindValue($i++, $v, PDO::PARAM_STR);
            }
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::stmt($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::stmt($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function value(string $sql, array $params = [])
    {
        $r = self::stmt($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function column(string $sql, array $params = []): array
    {
        return self::stmt($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** اجرای دستور و بازگرداندن تعداد ردیف‌های متأثر. */
    public static function run(string $sql, array $params = []): int
    {
        return self::stmt($sql, $params)->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        self::assertIdent($table);
        foreach ($cols as $c) {
            self::assertIdent($c);
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::stmt($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdent($table);
        $set = [];
        foreach (array_keys($data) as $c) {
            self::assertIdent($c);
            $set[] = '`' . $c . '` = ?';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . $where;
        return self::run($sql, array_merge(array_values($data), $whereParams));
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** رشته‌ی placeholder برای IN (...) */
    public static function in(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }

    /** escape برای LIKE */
    public static function like(string $s): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
    }

    private static function assertIdent(string $s): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $s);
        }
    }
}
