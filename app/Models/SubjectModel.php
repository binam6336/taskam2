<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class SubjectModel
{
    /** ۱۵ رنگ آماده‌ی لیبل‌ها (به‌جای انتخابگر رنگ آزاد) */
    public const COLORS = [
        '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
        '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#3b82f6',
        '#6366f1', '#8b5cf6', '#a855f7', '#ec4899', '#64748b',
    ];

    public static function forUser(int $uid): array
    {
        return DB::all('SELECT s.*, (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id) AS tasks_count FROM subjects s WHERE s.user_id = ? ORDER BY s.title', [$uid]);
    }

    public static function owned(int $id, int $uid): ?array
    {
        return DB::one('SELECT * FROM subjects WHERE id = ? AND user_id = ?', [$id, $uid]);
    }
}
