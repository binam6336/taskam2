<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class SubjectModel
{
    public static function forUser(int $uid): array
    {
        return DB::all('SELECT s.*, (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id) AS tasks_count FROM subjects s WHERE s.user_id = ? ORDER BY s.title', [$uid]);
    }

    public static function owned(int $id, int $uid): ?array
    {
        return DB::one('SELECT * FROM subjects WHERE id = ? AND user_id = ?', [$id, $uid]);
    }
}
