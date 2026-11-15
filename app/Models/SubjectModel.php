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

    /** لیبل‌های پیش‌فرض هر کاربر جدید (عنوان => رنگ) */
    public const DEFAULTS = ['پیگیری' => '#3b82f6', 'امکان‌سنجی' => '#8b5cf6', 'فوری' => '#ef4444'];

    /** شرط «پروژه‌هایی که کاربر می‌بیند» (سازنده یا عضو، مسدودنشده) روی ستون $col */
    private static function projectsSql(string $col): string
    {
        return "$col IN (SELECT p.id FROM projects p WHERE p.blocked_at IS NULL AND (p.user_id = ? OR p.id IN (SELECT pm.project_id FROM project_members pm WHERE pm.user_id = ?)))";
    }

    /** لیبل‌های پیش‌فرض یک پروژه‌ی تازه (v7.12: لیبل‌ها مال پروژه‌اند، نه هر کاربر) */
    public static function seedProject(int $projectId, int $creatorId): void
    {
        foreach (self::DEFAULTS as $title => $color) {
            DB::insert('subjects', ['user_id' => $creatorId, 'project_id' => $projectId, 'title' => $title, 'color' => $color]);
        }
    }

    /**
     * لیبل‌هایی که کاربر می‌تواند ببیند و روی تسک بگذارد: لیبل‌های همه‌ی پروژه‌هایش (مسدودنشده) با نام پروژه و can_manage.
     * $legacy: لیبل‌های شخصی قدیمی خودش (قبل از v7.12، بدون پروژه) هم بیاید — فقط صفحه‌ی «لیبل‌ها» برای انتقال به پروژه.
     */
    public static function forUser(int $uid, bool $legacy = false): array
    {
        $rows = DB::all('SELECT s.*, p.title AS project_title, (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id AND t.blocked_at IS NULL) AS tasks_count
                FROM subjects s LEFT JOIN projects p ON p.id = s.project_id
                WHERE s.blocked_at IS NULL AND (' . self::projectsSql('s.project_id') . ($legacy ? ' OR (s.project_id IS NULL AND s.user_id = ?)' : '') . ')
                ORDER BY s.project_id IS NULL, p.title, s.title', $legacy ? [$uid, $uid, $uid] : [$uid, $uid]);   // مسدودشده‌ها فقط در «لیبل‌های مسدودشده»
        foreach ($rows as &$r) {
            $r['can_manage'] = $r['project_id'] === null ? (int) $r['user_id'] === $uid : \App\Services\Access::canManageLabels((int) $r['project_id'], $uid);
        }
        return $rows;
    }

    /** لیبل‌های فعال یک پروژه */
    public static function forProject(int $projectId): array
    {
        return DB::all('SELECT * FROM subjects WHERE project_id = ? AND blocked_at IS NULL ORDER BY title', [$projectId]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM subjects WHERE id = ?', [$id]);
    }

    /** لیبل قابل گذاشتن روی تسکی از پروژه‌ی $projectId: مال همان پروژه و مسدودنشده */
    public static function usableIn(int $id, int $projectId): bool
    {
        return $projectId > 0 && (bool) DB::value('SELECT 1 FROM subjects WHERE id = ? AND project_id = ? AND blocked_at IS NULL', [$id, $projectId]);
    }

    /** لیبل شخصی قدیمی خود کاربر (بدون پروژه) */
    public static function owned(int $id, int $uid): ?array
    {
        return DB::one('SELECT * FROM subjects WHERE id = ? AND user_id = ? AND project_id IS NULL', [$id, $uid]);
    }

    /** عنوان تکراری در همان پروژه (بدون حساسیت به حروف و فاصله‌ی اضافه) */
    public static function titleTaken(int $projectId, string $title, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM subjects WHERE project_id = ? AND id <> ? AND blocked_at IS NULL AND TRIM(title) = ? LIMIT 1', [$projectId, $exceptId, trim($title)]);
    }

    /**
     * انتقال لیبل‌های شخصی قبل از v7.12 به پروژه‌ها (یک‌بار، Schema::PHP_DATA 047):
     *  - هر لیبل شخصی به پروژه‌ای می‌رود که تسک‌هایش آن را دارند؛ اگر در چند پروژه استفاده شده، برای هر پروژه‌ی دیگر یک لیبل
     *    هم‌نام ساخته و تسک‌های همان پروژه به آن وصل می‌شوند؛ لیبل هم‌نام موجود در پروژه دوباره ساخته نمی‌شود (ادغام).
     *  - پروژه‌ی بدون لیبل، لیبل‌های پیش‌فرض می‌گیرد.
     *  - اعضایی که همه‌ی دسترسی‌های قبلی پروژه را داشتند (نقش مدیر کل / مدیر واحد) «دسترسی ایجاد لیبل» هم می‌گیرند تا نقششان عوض نشود.
     *  - لیبل شخصی که روی هیچ تسک پروژه‌داری نیست بدون پروژه می‌ماند (فقط در صفحه‌ی لیبل‌های صاحبش برای انتقال یا مسدود کردن).
     */
    public static function migrateToProjects(): void
    {
        $old = ['edit_project', 'add_member', 'remove_member', 'manage_permissions', 'view_analytics', 'manage_board'];
        foreach (DB::all('SELECT id, permissions FROM project_members WHERE permissions IS NOT NULL') as $m) {
            $p = json_decode((string) $m['permissions'], true);
            if (is_array($p) && !array_diff($old, $p) && !in_array('create_label', $p, true)) {
                $p[] = 'create_label';
                DB::run('UPDATE project_members SET permissions = ? WHERE id = ?', [json_encode(array_values($p)), (int) $m['id']]);
            }
        }
        $pairs = DB::all('SELECT DISTINCT t.subject_id, t.project_id FROM tasks t INNER JOIN subjects s ON s.id = t.subject_id
                WHERE s.project_id IS NULL AND t.project_id IS NOT NULL ORDER BY t.project_id, t.subject_id');
        foreach ($pairs as $pr) {
            $sid = (int) $pr['subject_id'];
            $pj = (int) $pr['project_id'];
            $s = self::find($sid);
            if ($s === null) {
                continue;
            }
            $target = (int) DB::value('SELECT id FROM subjects WHERE project_id = ? AND TRIM(title) = ? ORDER BY blocked_at IS NOT NULL, id LIMIT 1', [$pj, trim((string) $s['title'])]);
            if ($target === 0) {
                if ($s['project_id'] === null) {
                    DB::run('UPDATE subjects SET project_id = ? WHERE id = ?', [$pj, $sid]);
                    $target = $sid;
                } else {
                    $target = DB::insert('subjects', ['user_id' => (int) $s['user_id'], 'project_id' => $pj, 'title' => $s['title'], 'color' => $s['color'],
                        'blocked_at' => $s['blocked_at'], 'blocked_by' => $s['blocked_by']]);
                }
            }
            if ($target !== $sid) {
                DB::run('UPDATE tasks SET subject_id = ? WHERE subject_id = ? AND project_id = ?', [$target, $sid, $pj]);
            }
        }
        foreach (DB::all('SELECT p.id, p.user_id FROM projects p WHERE NOT EXISTS (SELECT 1 FROM subjects s WHERE s.project_id = p.id)') as $p) {
            self::seedProject((int) $p['id'], (int) $p['user_id']);
        }
    }
}
