<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class TextModel
{
    public static function forUser(int $uid, string $search = ''): array
    {
        if ($search !== '') {
            $l = DB::like($search);
            return DB::all('SELECT * FROM texts WHERE user_id = ? AND (title LIKE ? OR content LIKE ?) ORDER BY sort_order ASC, created_at DESC', [$uid, $l, $l]);
        }
        return DB::all('SELECT * FROM texts WHERE user_id = ? ORDER BY sort_order ASC, created_at DESC', [$uid]);
    }

    public static function owned(string $id, int $uid): ?array
    {
        return DB::one('SELECT * FROM texts WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function create(int $uid, string $title, string $content): array
    {
        $id = bin2hex(random_bytes(6)) . '_' . bin2hex(random_bytes(4));
        $min = (int) DB::value('SELECT COALESCE(MIN(sort_order), 0) FROM texts WHERE user_id = ?', [$uid]);
        DB::insert('texts', ['id' => $id, 'user_id' => $uid, 'title' => $title, 'content' => $content, 'sort_order' => $min - 1]);
        return self::owned($id, $uid) ?? [];
    }

    public static function reorder(int $uid, array $ids): void
    {
        DB::transaction(static function () use ($uid, $ids): void {
            foreach (array_values($ids) as $i => $id) {
                if (is_string($id) && preg_match('/^[A-Za-z0-9_]{1,50}$/', $id)) {
                    DB::run('UPDATE texts SET sort_order = ? WHERE id = ? AND user_id = ?', [$i, $id, $uid]);
                }
            }
        });
    }
}
