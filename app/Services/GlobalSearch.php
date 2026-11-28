<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Search;
use App\Models\TaskModel;

/**
 * جستجوی سراسری نوار بالا (v7.13) — مشترک بین پنل (GET /search) و API (GET /api/v1/search).
 * فقط چیزهایی که کاربر به آن‌ها دسترسی دارد:
 *  - تسک‌ها: همه‌ی تسک‌های پروژه‌هایی که سازنده یا عضوشان است (نه فقط تسک‌های خودش) + تسک‌های بدون پروژه‌ی خودش؛
 *    بدون مسدودشده‌ها و پروژه‌های مسدود، و تسک محرمانه فقط برای سازنده/مسئول/بیننده‌ی انتخابی (TaskModel::confidentialSql).
 *  - گزارش‌های تسک (یادداشت‌های task_notes) فقط روی همان تسک‌های قابل دیدن.
 *  - یادداشت‌ها، تیکت‌ها و همکاران: فقط مال پنل خود کاربر. پروژه‌ها و لیبل‌ها: پروژه‌هایی که عضوشان است.
 * هر کلمه‌ی جستجو باید در یکی از ستون‌ها باشد؛ عنوان تسک با غلط تایپی کم هم پیدا می‌شود (Search::fuzzyIds) وقتی جستجوی دقیق چیزی نیابد.
 */
final class GlobalSearch
{
    public const MIN_LEN = 2;
    public const MAX_LEN = 100;
    /** حداکثر نتیجه در هر گروه */
    public const PER_GROUP = 6;
    /** سقف حافظه هر نوبت بررسی؛ داده قابل دسترسی با پیمایش کلیدی خوانده می‌شود. */
    public const SCAN_BATCH = 128;

    public const GROUPS = [
        'tasks'    => ['تسک‌ها', 'fa-list-check'],
        'tasks_done' => ['انجام‌شده', 'fa-check'],
        'reports'  => ['گزارش‌های تسک', 'fa-comment-dots'],
        'projects' => ['پروژه‌ها', 'fa-diagram-project'],
        'notes'    => ['یادداشت‌ها', 'fa-note-sticky'],
        'labels'   => ['لیبل‌ها', 'fa-tags'],
        'people'   => ['همکاران', 'fa-user-group'],
        'tickets'  => ['تیکت‌های پشتیبانی', 'fa-headset'],
    ];

    /** پروژه‌هایی که کاربر می‌بیند (سازنده یا عضو، مسدودنشده) — شرط SQL روی ستون $col با دو پارامتر uid */
    private static function projectsIn(string $col): string
    {
        return "$col IN (SELECT p.id FROM projects p WHERE p.blocked_at IS NULL AND (p.user_id = ? OR p.id IN (SELECT pm.project_id FROM project_members pm WHERE pm.user_id = ?)))";
    }

    /** شرط تسک‌های قابل دیدن در جستجو (با پارامترهایش) */
    private static function taskScope(int $uid, string $t = 't'): array
    {
        return ["$t.blocked_at IS NULL AND (" . self::projectsIn("$t.project_id") . " OR ($t.project_id IS NULL AND ($t.user_id = ? OR $t.assignee_id = ?))) AND " . TaskModel::confidentialSql($uid, $t),
            [$uid, $uid, $uid, $uid]];
    }

    /** @return array{query:string, total:int, groups:array} */
    public static function run(int $uid, string $q): array
    {
        $q = trim(mb_substr($q, 0, self::MAX_LEN));
        $out = ['query' => $q, 'total' => 0, 'groups' => []];
        if (mb_strlen(Search::norm($q)) < self::MIN_LEN || !Search::tokens($q)) {
            return $out;
        }
        $n = self::PER_GROUP;
        $tasks = self::tasks($uid, $q, $n);
        $groups = [
            'tasks'    => array_values(array_filter($tasks, static fn($t) => !$t['done'])),
            'tasks_done' => array_values(array_filter($tasks, static fn($t) => $t['done'])),
            'reports'  => self::reports($uid, $q, $n),
            'projects' => self::projects($uid, $q, $n),
            'notes'    => self::notes($uid, $q, $n),
            'labels'   => self::labels($uid, $q, $n),
            'people'   => self::people($uid, $q, $n),
            'tickets'  => self::tickets($uid, $q, $n),
        ];
        foreach ($groups as $key => $items) {
            if ($items) {
                $out['groups'][] = ['key' => $key, 'title' => self::GROUPS[$key][0], 'icon' => self::GROUPS[$key][1], 'items' => $items];
                $out['total'] += count($items);
            }
        }
        return $out;
    }

    /**
     * نخست مجوز در SQL، سپس تطبیق متن رمزگشایی‌شده؛ بدون اسکن کاربران دیگر یا ساخت IN از ciphertext.
     * cursor: نام خروجی => ستون SQL؛ تمام ستون‌ها ثابت داخلی و ترتیب نزولی است.
     * پس از تکمیل نتیجه متوقف می‌شود؛ برای موارد قدیمی، همه صفحه‌های مجاز قابل پیمایش‌اند.
     */
    private static function matchingRows(string $select, array $params, array $fields, string $q, array $cursor, int $limit): array
    {
        $tokens = Search::tokens($q);
        $out = []; $last = null;
        $order = implode(', ', array_map(static fn($column) => $column . ' DESC', array_values($cursor)));
        do {
            $where = ''; $pageParams = $params;
            if ($last !== null) {
                $alternatives = []; $equal = []; $equalParams = [];
                foreach ($cursor as $alias => $column) {
                    $alternatives[] = '(' . implode(' AND ', array_merge($equal, [$column . ' < ?'])) . ')';
                    $pageParams = array_merge($pageParams, $equalParams, [$last[$alias]]);
                    $equal[] = $column . ' = ?'; $equalParams[] = $last[$alias];
                }
                $where = ' AND (' . implode(' OR ', $alternatives) . ')';
            }
            $rows = DB::all($select . $where . ' ORDER BY ' . $order . ' LIMIT ' . self::SCAN_BATCH, $pageParams);
            foreach ($rows as $row) {
                $texts = array_map(static fn($field) => Search::norm((string) ($row[$field] ?? '')), $fields);
                $matches = true;
                foreach ($tokens as $token) {
                    if (!array_filter($texts, static fn($text) => mb_strpos($text, $token) !== false)) { $matches = false; break; }
                }
                if ($matches) { $out[] = $row; if (count($out) >= $limit) { return $out; } }
            }
            $last = $rows ? $rows[count($rows) - 1] : null;
        } while (count($rows) === self::SCAN_BATCH);
        return $out;
    }

    private static function taskItem(array $t, string $sub, ?string $snippet = null): array
    {
        $token = $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']);
        return ['id' => pid('tasks', $t['id']), 'title' => (string) $t['title'], 'sub' => $sub, 'snippet' => $snippet,
            'url' => url('/tasks?task=' . $token), 'open_task' => pid('tasks', $t['id']), 'done' => (int) $t['is_completed'] === 1];
    }

    private static function tasks(int $uid, string $q, int $n): array
    {
        [$scope, $sp] = self::taskScope($uid);
        $sel = 'SELECT t.id, t.title, t.description, t.share_token, t.is_completed, t.due_date, p.title AS project_title FROM tasks t LEFT JOIN projects p ON p.id = t.project_id';
        // سقف مستقل برای باز و انجام‌شده؛ تسک‌های باز، نتیجه‌های انجام‌شده را پنهان نمی‌کنند.
        $rows = [];
        foreach ([0, 1] as $done) {
            $rows = array_merge($rows, self::matchingRows("$sel WHERE $scope AND t.is_completed = $done", $sp, ['title', 'description'], $q, ['id' => 't.id'], $n));
        }
        if (!$rows) {
            // غلط تایپی کم در عنوان (همان قاعده‌ی فهرست تسک‌ها)
            $cand = DB::all("SELECT t.id, t.title FROM tasks t WHERE $scope ORDER BY t.id DESC LIMIT 3000", $sp);
            $ids = Search::fuzzyIds($cand, $q, ['title'], $n * 2);
            $rows = $ids ? DB::all("$sel WHERE $scope AND t.id IN (" . DB::in($ids) . ') ORDER BY t.is_completed ASC, t.id DESC', array_merge($sp, $ids)) : [];
            $counts = [0, 0];
            $rows = array_values(array_filter($rows, static function ($t) use (&$counts, $n): bool {
                return ++$counts[(int) $t['is_completed']] <= $n;
            }));
        }
        return array_map(static function (array $t) use ($q): array {
            $parts = array_filter([$t['project_title'] ? 'پروژه: ' . $t['project_title'] : 'بدون پروژه', (int) $t['is_completed'] === 1 ? 'انجام‌شده' : null,
                $t['due_date'] ? 'سررسید ' . jdate((string) $t['due_date'], false) : null]);
            $inTitle = self::contains((string) $t['title'], $q);
            return self::taskItem($t, implode('، ', $parts), $inTitle ? null : self::snippet((string) $t['description'], $q));
        }, $rows);
    }

    private static function reports(int $uid, string $q, int $n): array
    {
        [$scope, $sp] = self::taskScope($uid);
        $rows = self::matchingRows("SELECT n.id AS note_id, n.note, n.created_at, u.first_name, u.last_name, t.id, t.title, t.share_token, t.is_completed
                FROM task_notes n INNER JOIN tasks t ON t.id = n.task_id LEFT JOIN users u ON u.id = n.user_id
                WHERE $scope", $sp, ['note'], $q, ['note_id' => 'n.id'], $n);
        return array_map(static fn(array $r) => ['type_note' => true] + self::taskItem($r, 'گزارش ' . full_name($r) . '، ' . jdate((string) $r['created_at']), self::snippet((string) $r['note'], $q)), $rows);
    }

    private static function projects(int $uid, string $q, int $n): array
    {
        // جستجوی عنوان پروژه به شمارش تسک‌ها، محاسبه همه مجوزها یا رمزگشایی توضیحات نیاز ندارد.
        [$qs, $qp] = Search::sql(['p.title'], $q, false);
        $rows = DB::all("SELECT p.id, p.pid, p.title, p.profile_image, (p.user_id = ?) AS is_creator FROM projects p
            WHERE " . self::projectsIn('p.id') . " AND $qs ORDER BY p.created_at DESC, p.id DESC LIMIT " . $n, array_merge([$uid, $uid, $uid], $qp));
        return array_map(static fn(array $p) => ['id' => pid('projects', $p), 'title' => (string) $p['title'],
            'sub' => !empty($p['is_creator']) ? 'سازنده‌ی پروژه' : 'عضو پروژه', 'snippet' => null,
            'url' => url('/projects?focus=' . pid('projects', $p)), 'image' => project_image_url($p['profile_image'] ?? null)],
            $rows);
    }

    private static function notes(int $uid, string $q, int $n): array
    {
        $rows = self::matchingRows('SELECT id, title, content, updated_at FROM texts WHERE user_id = ?', [$uid],
            ['title', 'content'], $q, ['updated_at' => 'updated_at', 'id' => 'id'], $n);
        return array_map(static fn(array $r) => ['id' => (string) $r['id'], 'title' => trim((string) $r['title']) !== '' ? (string) $r['title'] : mb_substr(trim((string) $r['content']), 0, 60),
            'sub' => 'یادداشت', 'snippet' => self::snippet((string) $r['content'], $q), 'url' => url('/notes?focus=' . rawurlencode((string) $r['id']))], $rows);
    }

    private static function labels(int $uid, string $q, int $n): array
    {
        [$qs, $qp] = Search::sql(['s.title'], $q, false);
        $rows = DB::all('SELECT s.id, s.title, s.color, p.title AS project_title FROM subjects s INNER JOIN projects p ON p.id = s.project_id
                WHERE ' . self::projectsIn('s.project_id') . " AND $qs ORDER BY p.title, s.title LIMIT " . $n, array_merge([$uid, $uid], $qp));
        return array_map(static fn(array $r) => ['id' => pid('subjects', $r['id']), 'title' => (string) $r['title'], 'sub' => 'پروژه: ' . $r['project_title'], 'snippet' => null,
            'color' => (string) $r['color'], 'url' => url('/tasks?scope=all&status=all&label=' . pid('subjects', $r['id']))], $rows);
    }

    /** همکاران خود کاربر — فقط با نام (موبایل همکار تأییدنشده ماسک است؛ با شماره جستجو نمی‌شود) */
    private static function people(int $uid, string $q, int $n): array
    {
        [$qs, $qp] = Search::sql(["CONCAT(u.first_name, ' ', u.last_name)"], $q, false);
        $rows = DB::all("SELECT u.id, u.first_name, u.last_name, u.avatar FROM colleagues c INNER JOIN users u ON u.id = c.colleague_user_id
                WHERE c.user_id = ? AND u.status = 'active' AND u.is_bot = 0 AND $qs ORDER BY u.first_name, u.last_name LIMIT " . $n, array_merge([$uid], $qp));
        return array_map(static fn(array $r) => ['id' => pid('users', $r['id']), 'title' => full_name($r), 'sub' => 'همکار', 'snippet' => null,
            'url' => url('/colleagues'), 'avatar' => avatar_url($r['avatar'] ?? null)], $rows);
    }

    private static function tickets(int $uid, string $q, int $n): array
    {
        [$qs, $qp] = Search::sql(['subject'], $q, false);
        $st = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'answered' => 'پاسخ داده شد', 'closed' => 'بسته'];
        $rows = DB::all("SELECT id, subject, status FROM support_tickets WHERE user_id = ? AND $qs ORDER BY id DESC LIMIT " . $n, array_merge([$uid], $qp));
        return array_map(static fn(array $r) => ['id' => pid('support_tickets', $r['id']), 'title' => (string) $r['subject'], 'sub' => 'تیکت ' . ($st[$r['status']] ?? $r['status']),
            'snippet' => null, 'url' => url('/support/' . pid('support_tickets', $r['id']))], $rows);
    }

    // ───── کمکی‌ها ─────

    private static function contains(string $text, string $q): bool
    {
        $t = Search::norm($text);
        foreach (Search::tokens($q) as $w) {
            if (mb_strpos($t, $w) !== false) {
                return true;
            }
        }
        return false;
    }

    /** تکه‌ی کوتاه متن دور اولین کلمه‌ی پیداشده (متن ساده؛ فرار HTML با نمایش‌دهنده) */
    public static function snippet(string $text, string $q, int $len = 110): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if ($text === '') {
            return null;
        }
        $norm = Search::norm($text);
        $pos = null;
        foreach (Search::tokens($q) as $w) {
            $p = mb_strpos($norm, $w);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }
        if ($pos === null || mb_strlen($norm) !== mb_strlen($text)) {
            $pos = 0;                                           // یکسان‌سازی طول را عوض کرده: از ابتدا
        }
        $start = max(0, $pos - 30);
        $cut = mb_substr($text, $start, $len);
        return ($start > 0 ? '…' : '') . $cut . ($start + $len < mb_strlen($text) ? '…' : '');
    }
}
