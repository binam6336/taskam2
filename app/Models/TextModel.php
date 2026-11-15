<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * یادداشت‌های شخصی (v6.0؛ قبلاً «متن‌های آماده»): کارت‌های رنگی مثل یادداشت چسبان با ترتیب دستی. جدول texts همان است.
 */
final class TextModel
{
    /** رنگ‌های پس‌زمینه‌ی یادداشت (کلید => نام)؛ رنگ واقعی برای هر تم در app.css (.note-c-*) */
    public const COLORS = ['yellow' => 'زرد', 'green' => 'سبز', 'blue' => 'آبی', 'red' => 'قرمز', 'purple' => 'بنفش'];
    public const DEFAULT_COLOR = 'yellow';

    public static function color(?string $c): string
    {
        return isset(self::COLORS[(string) $c]) ? (string) $c : self::DEFAULT_COLOR;
    }

    public static function forUser(int $uid, string $search = ''): array
    {
        if ($search !== '') {
            [$qs, $qp] = \App\Core\Search::sql(['title', 'content'], $search);
            return DB::all('SELECT * FROM texts WHERE user_id = ? AND ' . $qs . ' ORDER BY sort_order ASC, created_at DESC', array_merge([$uid], $qp));
        }
        return DB::all('SELECT * FROM texts WHERE user_id = ? ORDER BY sort_order ASC, created_at DESC', [$uid]);
    }

    public static function owned(string $id, int $uid): ?array
    {
        return DB::one('SELECT * FROM texts WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function create(int $uid, string $title, string $content, string $color = self::DEFAULT_COLOR): array
    {
        $id = bin2hex(random_bytes(6)) . '_' . bin2hex(random_bytes(4));
        $min = (int) DB::value('SELECT COALESCE(MIN(sort_order), 0) FROM texts WHERE user_id = ?', [$uid]);
        DB::insert('texts', ['id' => $id, 'user_id' => $uid, 'title' => $title, 'content' => $content, 'color' => self::color($color), 'sort_order' => $min - 1]);
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
