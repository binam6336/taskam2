<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Pid;

/**
 * دپارتمان / گروه (v5.10): هر گروه مال یک پروژه است (project_groups)، اعضایش از اعضای همان پروژه‌اند (project_group_members)
 * و یک سرگروه اختیاری دارد (head_id). تسک می‌تواند به گروه داده شود (tasks.assignee_group_id) و تیک «انجام دادم»
 * هر عضو در task_group_ticks ثبت می‌شود.
 */
final class GroupModel
{
    public const MAX_TITLE = 80;
    public const MAX_DESC = 255;
    public const MAX_GROUPS = 50;          // سقف گروه در هر پروژه
    public const MAX_MEMBERS = 100;        // سقف اعضای هر گروه

    /** @var array<int,array<int,int>> کش اعضای هر گروه در همین درخواست */
    private static array $members = [];
    /** @var array<int,array<int,int>> کش گروه‌های هر کاربر */
    private static array $mine = [];

    public static function flush(): void
    {
        self::$members = [];
        self::$mine = [];
    }

    public static function find(int $id): ?array
    {
        return $id > 0 ? DB::one('SELECT * FROM project_groups WHERE id = ? LIMIT 1', [$id]) : null;
    }

    /** شناسه‌ی اعضای گروه */
    public static function memberIds(int $groupId): array
    {
        if (!isset(self::$members[$groupId])) {
            self::$members[$groupId] = array_map('intval', DB::column('SELECT user_id FROM project_group_members WHERE group_id = ? ORDER BY created_at, user_id', [$groupId]));
        }
        return self::$members[$groupId];
    }

    public static function isMember(int $groupId, int $uid): bool
    {
        return $groupId > 0 && $uid > 0 && in_array($uid, self::memberIds($groupId), true);
    }

    /** گروه‌هایی که کاربر عضو آن‌هاست */
    public static function groupsOf(int $uid): array
    {
        if (!isset(self::$mine[$uid])) {
            self::$mine[$uid] = array_map('intval', DB::column('SELECT group_id FROM project_group_members WHERE user_id = ?', [$uid]));
        }
        return self::$mine[$uid];
    }

    /** شرط SQL «تسک گروهی که کاربر عضو گروهش است» (شناسه‌ی کاربر عدد صحیح است و مستقیم در SQL می‌نشیند) */
    public static function memberSql(int $uid, string $t = 't'): string
    {
        return $t . '.assignee_group_id IN (SELECT pgm.group_id FROM project_group_members pgm WHERE pgm.user_id = ' . (int) $uid . ')';
    }

    /** گروه‌های یک پروژه با اعضا، سرگروه و تعداد تسک‌های باز */
    public static function forProject(int $projectId): array
    {
        return self::forProjects([$projectId]);
    }

    /** گروه‌های چند پروژه (صفحه‌ی دپارتمان / گروه)؛ $q = جستجو در نام گروه */
    public static function forProjects(array $projectIds, string $q = ''): array
    {
        $projectIds = array_values(array_filter(array_map('intval', $projectIds)));
        if (!$projectIds) {
            return [];
        }
        $params = $projectIds;
        $where = 'g.project_id IN (' . DB::in($projectIds) . ')';
        if ($q !== '') {
            $where .= ' AND g.title LIKE ?';
            $params[] = DB::like($q);
        }
        $rows = DB::all("SELECT g.*, p.title AS project_title, p.profile_image AS project_image,
                (SELECT COUNT(*) FROM tasks t WHERE t.assignee_group_id = g.id AND t.is_completed = 0 AND t.blocked_at IS NULL) open_tasks,
                (SELECT COUNT(*) FROM tasks t WHERE t.assignee_group_id = g.id) all_tasks
            FROM project_groups g JOIN projects p ON p.id = g.project_id WHERE $where ORDER BY p.title, g.title LIMIT 500", $params);
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($g) => (int) $g['id'], $rows);
        $mem = [];
        foreach (DB::all('SELECT m.group_id, u.id, u.first_name, u.last_name, u.avatar, u.mobile FROM project_group_members m JOIN users u ON u.id = m.user_id
                WHERE m.group_id IN (' . DB::in($ids) . ') ORDER BY m.created_at, u.id', $ids) as $m) {
            $mem[(int) $m['group_id']][] = $m;
        }
        foreach ($rows as &$g) {
            $g['members'] = $mem[(int) $g['id']] ?? [];
            self::$members[(int) $g['id']] = array_map(static fn($m) => (int) $m['id'], $g['members']);
        }
        return $rows;
    }

    /** گزینه‌های «گروه» در فرم تسک: پروژه => [{id, title, head, members}] برای پروژه‌های کاربر (فقط گروه‌های دارای عضو) */
    public static function optionsFor(array $projectIds): array
    {
        $projectIds = array_values(array_filter(array_map('intval', $projectIds)));
        if (!$projectIds) {
            return [];
        }
        $out = [];
        $groups = DB::all('SELECT g.id, g.project_id, g.title, g.head_id, (SELECT COUNT(*) FROM project_group_members m WHERE m.group_id = g.id) n
            FROM project_groups g WHERE g.project_id IN (' . DB::in($projectIds) . ') ORDER BY g.title', $projectIds);
        foreach ($groups as $g) {
            if ((int) $g['n'] === 0) {
                continue;
            }
            $out[Pid::of('projects', (int) $g['project_id'])][] = [
                'id' => Pid::of('project_groups', (int) $g['id']), 'title' => $g['title'], 'count' => (int) $g['n'],
                'has_head' => $g['head_id'] !== null,
            ];
        }
        return $out;
    }

    /** تیک‌های اعضا روی یک تسک گروهی: user_id => زمان */
    public static function ticks(int $taskId): array
    {
        return array_column(DB::all('SELECT user_id, created_at FROM task_group_ticks WHERE task_id = ?', [$taskId]), 'created_at', 'user_id');
    }

    public static function tick(int $taskId, int $uid, bool $on): void
    {
        if ($on) {
            DB::run('INSERT IGNORE INTO task_group_ticks (task_id, user_id) VALUES (?, ?)', [$taskId, $uid]);
        } else {
            DB::run('DELETE FROM task_group_ticks WHERE task_id = ? AND user_id = ?', [$taskId, $uid]);
        }
    }

    public static function clearTicks(int $taskId): void
    {
        DB::run('DELETE FROM task_group_ticks WHERE task_id = ?', [$taskId]);
    }
}
