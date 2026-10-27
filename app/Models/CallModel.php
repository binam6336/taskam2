<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class CallModel
{
    private const SELECT = 'SELECT r.*, ua.first_name AS assignee_first_name, ua.last_name AS assignee_last_name, ua.mobile AS assignee_mobile, ua.avatar AS assignee_avatar,
            uc.first_name AS creator_first_name, uc.last_name AS creator_last_name, uc.mobile AS creator_mobile, uc.avatar AS creator_avatar
        FROM call_requests r LEFT JOIN users ua ON ua.id = r.assignee_id LEFT JOIN users uc ON uc.id = r.user_id';

    public static function find(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE r.id = ?', [$id]);
    }

    public static function findByToken(string $token): ?array
    {
        return DB::one(self::SELECT . ' WHERE r.share_token = ?', [$token]);
    }

    /** @return array{rows:array,total:int} */
    public static function paginate(int $uid, int $status, int $limit, int $offset, string $q = ''): array
    {
        $w = '(r.user_id = ? OR r.assignee_id = ?) AND r.status = ?';
        $p = [$uid, $uid, $status];
        if ($q !== '') {
            $w .= ' AND (r.first_name LIKE ? OR r.last_name LIKE ? OR r.mobile LIKE ? OR r.store LIKE ?)';
            $l = DB::like($q);
            array_push($p, $l, $l, $l, $l);
        }
        $total = (int) DB::value("SELECT COUNT(*) FROM call_requests r WHERE $w", $p);
        $rows = DB::all(self::SELECT . " WHERE $w ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset]));
        return ['rows' => $rows, 'total' => $total];
    }

    public static function apiList(int $uid, string $status, string $q, int $limit, int $offset): array
    {
        $w = '(r.user_id = ? OR r.assignee_id = ?)';
        $p = [$uid, $uid];
        if ($status === 'new') {
            $w .= ' AND r.status = 0';
        } elseif ($status === 'done') {
            $w .= ' AND r.status = 1';
        }
        if ($q !== '') {
            $w .= ' AND (r.first_name LIKE ? OR r.last_name LIKE ? OR r.mobile LIKE ? OR r.store LIKE ?)';
            $l = DB::like($q);
            array_push($p, $l, $l, $l, $l);
        }
        return DB::all(self::SELECT . " WHERE $w ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset]));
    }

    public static function ensureToken(int $id): string
    {
        $t = (string) DB::value('SELECT share_token FROM call_requests WHERE id = ?', [$id]);
        if ($t !== '') {
            return $t;
        }
        DB::run('UPDATE call_requests SET share_token = ? WHERE id = ? AND share_token IS NULL', [bin2hex(random_bytes(16)), $id]);
        return (string) DB::value('SELECT share_token FROM call_requests WHERE id = ?', [$id]);
    }

    public static function stats(int $uid): array
    {
        $r = DB::one('SELECT COUNT(*) total, COALESCE(SUM(status = 0),0) new, COALESCE(SUM(status = 1),0) done FROM call_requests WHERE user_id = ? OR assignee_id = ?', [$uid, $uid]) ?? [];
        return array_map('intval', $r + ['total' => 0, 'new' => 0, 'done' => 0]);
    }
}
