<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\DB;
use App\Core\Pid;
use App\Models\TaskModel;
use App\Services\Access;
use App\Services\TaskAnalytics;
use App\Services\TaskHistory;
use App\Services\WorkloadService;

/**
 * ابزارهای فقط‌خواندنی دستیار بورد (v7.20). هر ابزار از دیتابیس (همان قواعد دسترسی بورد، BoardContext::universe) محاسبه می‌شود
 * و سه خروجی دارد: data (خلاصه‌ی فشرده برای مدل، با ارجاع کوتاه)، blocks (بلوک‌های نمایشی رابط) و summary (یک خط برای تاریخچه).
 * مدل هیچ آماری را خودش حساب نمی‌کند؛ عددها همین‌جا ساخته و مستقیم در رابط نمایش داده می‌شوند.
 */
final class BoardTools
{
    public const PER_PAGE = 10;
    /** سقف تسک‌های یک لیست */
    public const SET_MAX = 500;

    private const PRIO = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

    private static function out(array $data, array $blocks, string $summary): array
    {
        return ['data' => $data, 'blocks' => $blocks, 'summary' => $summary];
    }

    private static function user(BoardContext $c, ?int $uid): array
    {
        return $uid === null ? ['t' => 's', 'v' => 'بدون مسئول'] : ['t' => 'user', 'id' => pid('users', $uid), 'v' => (string) $c->name($uid)];
    }

    // ───────────────────────── لیست‌ها (result sets) ─────────────────────────

    /** ساخت لیست قابل ارجاع؛ خروجی [ردیف لیست، بلوک نمایش] */
    public static function makeSet(BoardContext $c, string $title, array $ids, array $meta = []): array
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::SET_MAX);
        $sid = (int) $c->session['id'];
        $seq = (int) DB::value('SELECT COALESCE(MAX(seq), 0) + 1 FROM ai_result_sets WHERE session_id = ?', [$sid]);
        $pid = Pid::new();
        $id = DB::insert('ai_result_sets', ['pid' => $pid, 'session_id' => $sid, 'seq' => $seq, 'title' => mb_substr($title, 0, 160),
            'task_ids' => json_encode($ids), 'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
        $set = ['id' => $id, 'pid' => $pid, 'seq' => $seq, 'title' => $title, 'task_ids' => json_encode($ids)];
        return [$set, self::setBlock($c, $set, 1)];
    }

    /** لیست‌های این گفتگو */
    public static function set(BoardContext $c, $ref): ?array
    {
        $sid = (int) $c->session['id'];
        if (is_int($ref)) {
            return DB::one('SELECT * FROM ai_result_sets WHERE id = ? AND session_id = ?', [$ref, $sid]);
        }
        if (is_string($ref) && preg_match('/^R(\d{1,4})$/i', trim($ref), $m)) {
            return DB::one('SELECT * FROM ai_result_sets WHERE session_id = ? AND seq = ?', [$sid, (int) $m[1]]);
        }
        if ($ref === 'last' || $ref === 'latest') {
            return DB::one('SELECT * FROM ai_result_sets WHERE session_id = ? ORDER BY seq DESC LIMIT 1', [$sid]);
        }
        return null;
    }

    /** شناسه‌های لیست که هنوز در دسترس کاربرند */
    public static function setIds(BoardContext $c, array $set): array
    {
        $u = $c->universe();
        return array_values(array_filter(array_map('intval', json_decode((string) $set['task_ids'], true) ?: []), static fn($id) => isset($u[$id])));
    }

    /** بلوک جدول یک صفحه از لیست */
    public static function setBlock(BoardContext $c, array $set, int $page): array
    {
        $all = array_map('intval', json_decode((string) $set['task_ids'], true) ?: []);
        $ids = self::setIds($c, $set);
        $pages = max(1, (int) ceil(count($ids) / self::PER_PAGE));
        $page = max(1, min($pages, $page));
        $u = $c->universe();
        $rows = array_map(static fn($id) => $c->row($u[$id]), array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE));
        return ['type' => 'tasks', 'set' => $set['pid'], 'ref' => 'R' . $set['seq'], 'title' => (string) $set['title'], 'total' => count($ids),
            'hidden' => count($all) - count($ids), 'page' => $page, 'pages' => $pages, 'rows' => $rows];
    }

    // ───────────────────────── list_tasks ─────────────────────────

    public static function listTasks(BoardContext $c, array $a): array
    {
        $u = $c->universe();
        $ids = array_keys($u);
        $desc = [];
        // زیرمجموعه‌ی لیست قبلی یا انتخاب کاربر
        if (!empty($a['selected']) && $c->selection['tasks']) {
            $ids = array_values(array_intersect($ids, $c->selection['tasks']));
            $desc[] = 'از تسک‌های انتخاب‌شده';
        } elseif (isset($a['from_set']) && ($set = self::set($c, $a['from_set']))) {
            $ids = self::setIds($c, $set);
            $desc[] = 'از لیست R' . $set['seq'];
        } elseif (empty($a['ignore_board_filters']) && ($f = $c->boardFilterIds()) !== null) {
            $ids = array_values(array_intersect($ids, $f));
            $desc[] = 'با فیلتر فعال بورد';
        }
        $cols = $c->columns();
        $status = $a['status'] ?? null;
        $status = is_string($status) ? [$status] : (is_array($status) ? array_values(array_filter($status, 'is_string')) : []);
        $wantDone = !empty($a['include_done']) || array_intersect($status, ['done', 'archived', 'all']);
        $wantArchived = !empty($a['include_archived']) || in_array('archived', $status, true);
        $statusKeys = array_values(array_filter($status, static fn($s) => isset($cols[$s]) || $s === 'archived'));
        if ($statusKeys) {
            $desc[] = 'ستون ' . implode('، ', array_map(static fn($s) => $s === 'archived' ? 'آرشیو' : $cols[$s], $statusKeys));
        }
        $today = Common::today()->format('Y-m-d');
        $plus = static fn(int $d) => Common::today()->modify('+' . $d . ' day')->format('Y-m-d');
        // ارجاع‌ها
        $assignees = [];
        $noAssignee = false;
        foreach ((array) ($a['assignee'] ?? []) as $r) {
            if ($r === 'me') {
                $assignees[] = $c->uid;
            } elseif ($r === 'none') {
                $noAssignee = true;
            } elseif (($id = $c->idOf('u', $r)) !== null) {
                $assignees[] = $id;
            }
        }
        if (($a['assignee'] ?? null) !== null && !$assignees && !$noAssignee) {
            return self::out(['error' => 'unknown assignee ref; use refs from MEMBERS'], [], 'list_tasks: مسئول نامعتبر');
        }
        $labelIds = [];
        $noLabel = false;
        foreach ((array) ($a['label'] ?? []) as $r) {
            if ($r === 'none') {
                $noLabel = true;
            } elseif (($id = $c->idOf('l', $r)) !== null) {
                $labelIds[] = $id;
            }
        }
        if (($a['label'] ?? null) !== null && !$labelIds && !$noLabel) {
            return self::out(['error' => 'unknown label ref; use refs from LABELS'], [], 'list_tasks: لیبل نامعتبر');
        }
        $from = isset($a['due_from']) && is_string($a['due_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['due_from']) ? $a['due_from'] : null;
        $to = isset($a['due_to']) && is_string($a['due_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['due_to']) ? $a['due_to'] : null;
        $due = is_string($a['due'] ?? null) ? $a['due'] : '';
        $prio = is_string($a['priority'] ?? null) && isset(self::PRIO[$a['priority']]) ? $a['priority'] : null;
        $stale = isset($a['stale_days']) && is_numeric($a['stale_days']) ? max(1, min(365, (int) $a['stale_days'])) : null;
        $q = is_string($a['q'] ?? null) ? Assistant::norm(mb_substr($a['q'], 0, 80)) : '';
        $overdue = !empty($a['overdue']) || $due === 'overdue';

        $out = [];
        foreach ($ids as $id) {
            $t = $u[$id];
            if ($t['status'] === 'archived' && !$wantArchived) {
                continue;
            }
            if ($t['status'] === 'done' && !$wantDone && !in_array('done', $statusKeys, true)) {
                continue;
            }
            if ($statusKeys && !in_array($t['status'], $statusKeys, true)) {
                continue;
            }
            if ($overdue && !$t['overdue']) {
                continue;
            }
            $tid = $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null;
            if (($assignees || $noAssignee) && !(($tid !== null && in_array($tid, $assignees, true)) || ($noAssignee && $tid === null))) {
                continue;
            }
            $lid = $t['subject_id'] !== null ? (int) $t['subject_id'] : null;
            if (($labelIds || $noLabel) && !(($lid !== null && in_array($lid, $labelIds, true)) || ($noLabel && $lid === null))) {
                continue;
            }
            if ($prio !== null && $t['priority'] !== $prio) {
                continue;
            }
            $d = $t['due_date'];
            if ($due === 'today' && $d !== $today || $due === 'tomorrow' && $d !== $plus(1) || $due === 'none' && $d !== null || $due === 'has' && $d === null) {
                continue;
            }
            if (($due === 'next_7_days' || $due === 'this_week') && ($d === null || $d < $today || $d > ($due === 'this_week' ? Common::now()['week_end_friday'] : $plus(7)))) {
                continue;
            }
            if (($from !== null && ($d === null || $d < $from)) || ($to !== null && ($d === null || $d > $to))) {
                continue;
            }
            if ($stale !== null && ($t['done'] || substr($t['last_activity'], 0, 10) > Common::today()->modify('-' . $stale . ' day')->format('Y-m-d'))) {
                continue;
            }
            if ($q !== '' && mb_strpos(Assistant::norm((string) $t['title'] . ' ' . (string) $t['description']), $q) === false) {
                continue;
            }
            $out[] = $t;
        }
        $sort = is_string($a['sort'] ?? null) ? $a['sort'] : 'due';
        usort($out, static function ($x, $y) use ($sort) {
            $p = ['high' => 0, 'medium' => 1, 'low' => 2];
            switch ($sort) {
                case 'priority':
                    return [$p[$x['priority']] ?? 1, $x['due_date'] ?? '9999'] <=> [$p[$y['priority']] ?? 1, $y['due_date'] ?? '9999'];
                case 'updated':
                    return strcmp($x['last_activity'], $y['last_activity']);
                case 'created':
                    return strcmp((string) $y['created_at'], (string) $x['created_at']);
                case 'title':
                    return strcmp((string) $x['title'], (string) $y['title']);
                default:
                    return [$x['due_date'] === null ? 1 : 0, $x['due_date'] ?? '', (string) $x['due_time']] <=> [$y['due_date'] === null ? 1 : 0, $y['due_date'] ?? '', (string) $y['due_time']];
            }
        });
        // عنوان لیست از روی فیلترهای واقعی (نه متن مدل)
        if ($overdue) {
            $desc[] = 'عقب‌افتاده';
        }
        if ($assignees) {
            $desc[] = 'مسئول: ' . implode('، ', array_map(static fn($x) => (string) $c->name($x), $assignees));
        }
        if ($noAssignee) {
            $desc[] = 'بدون مسئول';
        }
        if ($labelIds) {
            $desc[] = 'لیبل: ' . implode('، ', array_map(static fn($x) => $c->labels()[$x][0] ?? '', $labelIds));
        }
        if ($noLabel) {
            $desc[] = 'بدون لیبل';
        }
        $dueNames = ['today' => 'سررسید امروز', 'tomorrow' => 'سررسید فردا', 'next_7_days' => 'سررسید ۷ روز آینده', 'this_week' => 'سررسید تا آخر هفته', 'none' => 'بدون سررسید', 'has' => 'دارای سررسید'];
        if (isset($dueNames[$due])) {
            $desc[] = $dueNames[$due];
        }
        if ($from !== null || $to !== null) {
            $desc[] = 'سررسید ' . ($from ? 'از ' . jdate($from . ' 00:00:00', false) : '') . ($to ? ' تا ' . jdate($to . ' 00:00:00', false) : '');
        }
        if ($prio !== null) {
            $desc[] = 'اولویت ' . self::PRIO[$prio];
        }
        if ($stale !== null) {
            $desc[] = 'بدون تغییر در ' . fa_num($stale) . ' روز اخیر';
        }
        if ($q !== '') {
            $desc[] = '«' . mb_substr($q, 0, 40) . '»';
        }
        if (!$wantDone && !$statusKeys) {
            $desc[] = 'باز';
        }
        $title = 'تسک‌ها' . ($desc ? ' — ' . implode('، ', $desc) : '');
        [$set, $block] = self::makeSet($c, $title, array_map(static fn($t) => (int) $t['id'], $out), ['args' => $a]);
        $data = ['result_set' => 'R' . $set['seq'], 'total' => count($out), 'title' => $title,
            'first' => array_map([$c, 'brief'], array_slice($out, 0, 15))];
        if (count($out) > self::SET_MAX) {
            $data['note'] = 'only first ' . self::SET_MAX . ' kept';
        }
        return self::out($data, [$block], 'list_tasks → R' . $set['seq'] . ' (' . count($out) . ' تسک)');
    }

    // ───────────────────────── جزئیات یک تسک ─────────────────────────

    private static function taskOf(BoardContext $c, $ref): ?array
    {
        $id = $c->idOf('t', $ref);
        return $id !== null ? $c->task($id) : null;
    }

    public static function getTask(BoardContext $c, array $a): array
    {
        $t = self::taskOf($c, $a['task'] ?? null);
        if ($t === null) {
            return self::out(['error' => 'unknown task ref (use T refs from previous tool results)'], [], 'get_task: ارجاع نامعتبر');
        }
        $data = $c->brief($t) + [
            'description' => $t['description'] !== null ? mb_substr((string) $t['description'], 0, 1200) : null,
            'creator' => $c->ref('u', (int) $t['user_id']) . ' ' . $c->name((int) $t['user_id']),
            'created' => substr((string) $t['created_at'], 0, 16), 'last_activity' => substr((string) $t['last_activity'], 0, 16),
            'progress_percent' => $t['progress'], 'delivered_at' => $t['delivered_at'], 'rework_count' => (int) $t['rework_count'],
            'confidential' => (bool) $t['is_confidential'], 'notes_count' => (int) DB::value('SELECT COUNT(*) FROM task_notes WHERE task_id = ?', [(int) $t['id']]),
        ];
        return self::out($data, [], 'get_task ' . $data['ref']);
    }

    public static function activity(BoardContext $c, array $a): array
    {
        $t = self::taskOf($c, $a['task'] ?? null);
        if ($t === null) {
            return self::out(['error' => 'unknown task ref'], [], 'get_task_activity: ارجاع نامعتبر');
        }
        $items = [];
        foreach (TaskHistory::forTask($t) as $i) {
            if ($i['type'] === 'note' || $i['type'] === 'file') {
                continue;
            }
            $d = $i['data'];
            unset($d['from_user']['avatar'], $d['to_user']['avatar'], $d['assignee_user']);
            $items[] = ['at' => $i['at'], 'type' => $i['type'], 'by' => $i['actor']['name'] ?? null, 'data' => self::clip($d)];
        }
        return self::out(['task' => $c->ref('t', (int) $t['id']), 'events' => array_slice($items, -25)], [], 'get_task_activity ' . $c->ref('t', (int) $t['id']) . ' (' . count($items) . ')');
    }

    public static function comments(BoardContext $c, array $a): array
    {
        $t = self::taskOf($c, $a['task'] ?? null);
        if ($t === null || !Access::canTask($t, $c->uid, 'view')) {
            return self::out(['error' => 'unknown task ref'], [], 'get_task_comments: ارجاع نامعتبر');
        }
        $rows = DB::all('SELECT n.note, n.created_at, u.first_name, u.last_name, u.is_bot FROM task_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.task_id = ? ORDER BY n.id DESC LIMIT 12', [(int) $t['id']]);
        $out = array_reverse(array_map(static fn($r) => ['by' => ((int) $r['is_bot'] === 1 ? 'Karlix (bot)' : trim($r['first_name'] . ' ' . $r['last_name'])),
            'at' => substr((string) $r['created_at'], 0, 16), 'text' => mb_substr((string) $r['note'], 0, 400)], $rows));
        return self::out(['task' => $c->ref('t', (int) $t['id']), 'comments_untrusted' => $out], [], 'get_task_comments ' . $c->ref('t', (int) $t['id']) . ' (' . count($out) . ')');
    }

    /** مقدارهای داده‌ی رویداد کوتاه می‌شوند */
    private static function clip(array $d): array
    {
        array_walk_recursive($d, static function (&$v) {
            if (is_string($v)) {
                $v = mb_substr($v, 0, 160);
            }
        });
        return $d;
    }

    // ───────────────────────── آمار بورد ─────────────────────────

    /** تسک‌های باز (نه انجام‌شده/آرشیو) */
    private static function open(BoardContext $c): array
    {
        return array_filter($c->universe(), static fn($t) => !$t['done']);
    }

    public static function boardStats(BoardContext $c, array $a): array
    {
        $u = $c->universe();
        $open = self::open($c);
        $today = Common::today()->format('Y-m-d');
        $in7 = Common::today()->modify('+7 day')->format('Y-m-d');
        $staleCut = Common::today()->modify('-7 day')->format('Y-m-d');
        $cols = $c->columns();
        $byCol = array_fill_keys(array_keys($cols), 0);
        $archived = 0;
        foreach ($u as $t) {
            $t['status'] === 'archived' ? $archived++ : $byCol[$t['status']] = ($byCol[$t['status']] ?? 0) + 1;
        }
        $overdue = array_filter($open, static fn($t) => $t['overdue']);
        $stale = array_filter($open, static fn($t) => substr($t['last_activity'], 0, 10) < $staleCut);
        $k = [
            'open' => count($open), 'overdue' => count($overdue),
            'due_today' => count(array_filter($open, static fn($t) => $t['due_date'] === $today)),
            'due_next_7_days' => count(array_filter($open, static fn($t) => $t['due_date'] !== null && $t['due_date'] > $today && $t['due_date'] <= $in7)),
            'waiting_review' => $byCol['review'] ?? 0, 'rework' => $byCol['rework'] ?? 0, 'no_assignee' => count(array_filter($open, static fn($t) => $t['assignee_id'] === null)),
            'no_due_date' => count(array_filter($open, static fn($t) => $t['due_date'] === null)), 'stale_7_days' => count($stale),
            'done' => $byCol['done'] ?? 0, 'archived' => $archived,
        ];
        $byPrio = ['high' => 0, 'medium' => 0, 'low' => 0];
        $byLabel = [];
        $byPerson = [];
        foreach ($open as $t) {
            $byPrio[$t['priority']] = ($byPrio[$t['priority']] ?? 0) + 1;
            $lk = $t['subject_id'] !== null ? (int) $t['subject_id'] : 0;
            $byLabel[$lk] ??= ['open' => 0, 'overdue' => 0];
            $byLabel[$lk]['open']++;
            $byLabel[$lk]['overdue'] += $t['overdue'] ? 1 : 0;
            $pk = $t['assignee_id'] !== null ? (int) $t['assignee_id'] : 0;
            $byPerson[$pk] ??= ['open' => 0, 'overdue' => 0];
            $byPerson[$pk]['open']++;
            $byPerson[$pk]['overdue'] += $t['overdue'] ? 1 : 0;
        }
        // گلوگاه‌ها (قاعده‌ی ثابت سرور)
        $bott = [];
        $mid = array_diff_key($byCol, array_flip(['done', 'review', 'rework']));
        arsort($mid);
        $topCol = key($mid);
        if ($topCol !== null && $k['open'] > 0 && $mid[$topCol] / max(1, $k['open']) >= 0.4 && $mid[$topCol] >= 3) {
            $bott[] = 'ستون «' . $cols[$topCol] . '» ' . fa_num($mid[$topCol]) . ' تسک از ' . fa_num($k['open']) . ' تسک باز را دارد.';
        }
        if ($k['waiting_review'] >= 3) {
            $bott[] = fa_num($k['waiting_review']) . ' تسک منتظر «' . ($cols['review'] ?? 'بررسی') . '» است.';
        }
        uasort($byPerson, static fn($x, $y) => [$y['overdue'], $y['open']] <=> [$x['overdue'], $x['open']]);
        $topP = key($byPerson);
        if ($topP !== null && $byPerson[$topP]['overdue'] >= 2) {
            $bott[] = ($topP ? (string) $c->name($topP) : 'تسک‌های بدون مسئول') . ' بیشترین تسک عقب‌افتاده را دارد (' . fa_num($byPerson[$topP]['overdue']) . ').';
        }
        uasort($byLabel, static fn($x, $y) => $y['overdue'] <=> $x['overdue']);
        $topL = key($byLabel);
        if ($topL !== null && $byLabel[$topL]['overdue'] >= 2) {
            $bott[] = ($topL ? 'لیبل «' . ($c->labels()[$topL][0] ?? '') . '»' : 'تسک‌های بدون لیبل') . ' بیشترین عقب‌افتادگی را دارد (' . fa_num($byLabel[$topL]['overdue']) . ').';
        }
        if ($k['stale_7_days'] >= 3) {
            $bott[] = fa_num($k['stale_7_days']) . ' تسک باز یک هفته است هیچ تغییر، یادداشت یا جابه‌جایی نداشته‌اند.';
        }
        $blocks = [
            ['type' => 'kpis', 'items' => [
                ['label' => 'تسک باز', 'value' => fa_num($k['open'])],
                ['label' => 'عقب‌افتاده', 'value' => fa_num($k['overdue']), 'tone' => $k['overdue'] ? 'danger' : ''],
                ['label' => 'سررسید امروز', 'value' => fa_num($k['due_today'])],
                ['label' => 'منتظر بررسی', 'value' => fa_num($k['waiting_review'])],
                ['label' => 'راکد (۷ روز)', 'value' => fa_num($k['stale_7_days']), 'tone' => $k['stale_7_days'] ? 'warn' : ''],
                ['label' => 'بدون مسئول', 'value' => fa_num($k['no_assignee'])],
            ]],
            ['type' => 'table', 'title' => 'تعداد تسک در هر ستون', 'cols' => ['ستون', 'تعداد'],
                'rows' => array_values(array_map(static fn($key) => [['t' => 'column', 'id' => $key, 'v' => $cols[$key]], fa_num($byCol[$key])], array_keys($cols)))],
        ];
        if ($byLabel) {
            $blocks[] = ['type' => 'table', 'title' => 'تسک‌های باز به تفکیک لیبل', 'cols' => ['لیبل', 'باز', 'عقب‌افتاده'],
                'rows' => array_values(array_map(static fn($lid, $v) => [$lid ? ['t' => 'label', 'id' => pid('subjects', $lid), 'v' => $c->labels()[$lid][0] ?? ''] : 'بدون لیبل', fa_num($v['open']), fa_num($v['overdue'])], array_keys($byLabel), $byLabel))];
        }
        if ($bott) {
            $blocks[] = ['type' => 'notes', 'title' => 'گلوگاه‌ها و ریسک‌ها (قاعده‌ی خودکار)', 'items' => $bott];
        }
        $data = ['kpis' => $k, 'columns' => array_map(static fn($key) => ['key' => $key, 'label' => $cols[$key], 'count' => $byCol[$key]], array_keys($cols)),
            'by_priority_open' => $byPrio, 'bottlenecks' => $bott,
            'by_label_open' => array_map(static fn($lid, $v) => ['label' => $lid ? $c->ref('l', $lid) . ' ' . ($c->labels()[$lid][0] ?? '') : 'none'] + $v, array_keys($byLabel), $byLabel)];
        return self::out($data, $blocks, 'board_stats (باز ' . $k['open'] . '، عقب‌افتاده ' . $k['overdue'] . ')');
    }

    // ───────────────────────── بار کاری ─────────────────────────

    public static function workload(BoardContext $c, array $a): array
    {
        $u = $c->universe();
        $today = Common::today()->format('Y-m-d');
        $in7 = Common::today()->modify('+7 day')->format('Y-m-d');
        $d7 = date('Y-m-d H:i:s', strtotime('-7 days'));
        $d30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $rows = [];
        foreach (array_keys($c->members()) as $m) {
            $rows[$m] = ['open' => 0, 'overdue' => 0, 'due_7' => 0, 'review' => 0, 'rework' => 0, 'done_7' => 0, 'done_30' => 0, 'durations' => []];
        }
        $rows[0] = ['open' => 0, 'overdue' => 0, 'due_7' => 0, 'review' => 0, 'rework' => 0, 'done_7' => 0, 'done_30' => 0, 'durations' => []];
        foreach ($u as $t) {
            $k = $t['assignee_id'] !== null ? (int) $t['assignee_id'] : 0;
            $rows[$k] ??= ['open' => 0, 'overdue' => 0, 'due_7' => 0, 'review' => 0, 'rework' => 0, 'done_7' => 0, 'done_30' => 0, 'durations' => []];
            $r = &$rows[$k];
            if (!$t['done']) {
                $r['open']++;
                $r['overdue'] += $t['overdue'] ? 1 : 0;
                $r['due_7'] += $t['due_date'] !== null && $t['due_date'] >= $today && $t['due_date'] <= $in7 ? 1 : 0;
                $r['review'] += $t['status'] === 'review' ? 1 : 0;
                $r['rework'] += $t['status'] === 'rework' ? 1 : 0;
            } else {
                $fin = TaskAnalytics::finishedAt($t);
                if ($fin !== null) {
                    $r['done_7'] += $fin >= $d7 ? 1 : 0;
                    $r['done_30'] += $fin >= $d30 ? 1 : 0;
                    if ($fin >= date('Y-m-d H:i:s', strtotime('-120 days'))) {
                        $r['durations'][] = max(0, (strtotime($fin) - strtotime((string) $t['created_at'])) / 86400);
                    }
                }
            }
            unset($r);
        }
        if ($rows[0]['open'] === 0 && $rows[0]['done_30'] === 0) {
            unset($rows[0]);
        }
        uasort($rows, static fn($x, $y) => [$y['open'], $y['overdue']] <=> [$x['open'], $x['overdue']]);
        $data = [];
        $table = [];
        foreach ($rows as $k => $r) {
            $med = TaskAnalytics::median($r['durations']);
            $data[] = ['member' => $k ? $c->ref('u', $k) . ' ' . $c->name($k) : 'unassigned'] + array_diff_key($r, ['durations' => 1])
                + ['median_days_to_finish' => $med !== null ? round($med, 1) : null, 'sample' => count($r['durations'])];
            $table[] = [self::user($c, $k ?: null), fa_num($r['open']), fa_num($r['overdue']), fa_num($r['due_7']), fa_num($r['review']), fa_num($r['done_7']), fa_num($r['done_30']),
                $med !== null && count($r['durations']) >= 3 ? fa_num(round($med, 1)) . ' روز' : '—'];
        }
        $blocks = [['type' => 'table', 'title' => 'بار کاری به تفکیک مسئول (داده‌ی واقعی بورد)',
            'cols' => ['مسئول', 'باز', 'عقب‌افتاده', 'سررسید ۷ روز', 'منتظر بررسی', 'انجام ۷ روز', 'انجام ۳۰ روز', 'میانه‌ی زمان انجام'], 'rows' => $table,
            'note' => 'میانه‌ی زمان انجام = از ایجاد تا تحویل/تکمیل، ۱۲۰ روز اخیر، حداقل ۳ نمونه.']];
        return self::out(['members' => $data], $blocks, 'workload_by_assignee (' . count($data) . ' نفر)');
    }

    // ───────────────────────── سرعت و پیش‌بینی ─────────────────────────

    public static function velocity(BoardContext $c, array $a): array
    {
        $weeks = isset($a['weeks']) && is_numeric($a['weeks']) ? max(2, min(26, (int) $a['weeks'])) : 8;
        $lid = isset($a['label']) ? $c->idOf('l', $a['label']) : null;
        $who = isset($a['assignee']) ? ($a['assignee'] === 'me' ? $c->uid : $c->idOf('u', $a['assignee'])) : null;
        $u = array_filter($c->universe(), static fn($t) => ($lid === null || (int) $t['subject_id'] === $lid) && ($who === null || (int) $t['assignee_id'] === $who));
        // هفته‌ها از شنبه (تقویم شمسی)
        $start = Common::today()->modify('-' . (((int) Common::today()->format('w') + 1) % 7) . ' day');
        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $s = $start->modify('-' . (7 * $i) . ' day');
            $buckets[] = ['from' => $s->format('Y-m-d'), 'to' => $s->modify('+6 day')->format('Y-m-d'), 'created' => 0, 'finished' => 0];
        }
        $durations = [];
        foreach ($u as $t) {
            $cr = substr((string) $t['created_at'], 0, 10);
            $fin = $t['done'] ? TaskAnalytics::finishedAt($t) : null;
            foreach ($buckets as &$b) {
                if ($cr >= $b['from'] && $cr <= $b['to']) {
                    $b['created']++;
                }
                if ($fin !== null && substr($fin, 0, 10) >= $b['from'] && substr($fin, 0, 10) <= $b['to']) {
                    $b['finished']++;
                }
            }
            unset($b);
            if ($fin !== null && $fin >= date('Y-m-d', strtotime('-120 days'))) {
                $durations[] = max(0, (strtotime($fin) - strtotime((string) $t['created_at'])) / 86400);
            }
        }
        $last4 = array_slice($buckets, -4);
        $avg = array_sum(array_column($last4, 'finished')) / max(1, count($last4));
        $open = count(array_filter($u, static fn($t) => !$t['done']));
        $med = TaskAnalytics::median($durations);
        $forecast = null;
        if ($avg > 0 && $open > 0) {
            $w = $open / $avg;
            $fd = Common::today()->modify('+' . (int) ceil($w * 7) . ' day')->format('Y-m-d');
            $forecast = ['weeks' => round($w, 1), 'date' => $fd, 'date_jalali' => jdate($fd . ' 00:00:00', false, true), 'estimate' => true];
        }
        $scope = ($lid !== null ? 'لیبل «' . ($c->labels()[$lid][0] ?? '') . '»' : 'کل پروژه') . ($who !== null ? ' — ' . $c->name($who) : '');
        $blocks = [
            ['type' => 'kpis', 'items' => [
                ['label' => 'میانگین انجام در هفته (۴ هفته‌ی اخیر)', 'value' => fa_num(round($avg, 1))],
                ['label' => 'تسک باز', 'value' => fa_num($open)],
                ['label' => 'میانه‌ی زمان انجام', 'value' => $med !== null && count($durations) >= 3 ? fa_num(round($med, 1)) . ' روز' : '—'],
                ['label' => 'پیش‌بینی اتمام (تخمینی)', 'value' => $forecast ? jdate($forecast['date'] . ' 00:00:00', false) : 'نامشخص', 'tone' => 'warn'],
            ]],
            ['type' => 'table', 'title' => 'سرعت هفتگی — ' . $scope, 'cols' => ['هفته', 'ایجادشده', 'انجام‌شده'],
                'rows' => array_map(static fn($b) => [jdate($b['from'] . ' 00:00:00', false) . ' تا ' . jdate($b['to'] . ' 00:00:00', false), fa_num($b['created']), fa_num($b['finished'])], $buckets),
                'note' => 'پیش‌بینی فقط تخمینی است: تعداد تسک‌های باز تقسیم بر میانگین تسک‌های انجام‌شده در ۴ هفته‌ی اخیر؛ تسک‌های جدید و تفاوت حجم کارها در آن نیامده است.'],
        ];
        return self::out(['scope' => $scope, 'weeks' => $buckets, 'avg_finished_per_week_last4' => round($avg, 2), 'open' => $open,
            'median_days_to_finish' => $med !== null ? round($med, 1) : null, 'sample' => count($durations), 'forecast' => $forecast,
            'note' => 'forecast is an ESTIMATE; say so explicitly'], $blocks, 'velocity (' . $scope . ')');
    }

    /** پیشنهاد سررسید بر اساس میانه‌ی زمان انجام مسئول (یا پروژه) — فقط پیشنهاد؛ اعمال با preview_bulk_update */
    public static function suggestDeadline(BoardContext $c, array $a): array
    {
        $ids = [];
        if (isset($a['task']) && ($id = $c->idOf('t', $a['task'])) !== null) {
            $ids = [$id];
        } elseif (!empty($a['selected'])) {
            $ids = $c->selection['tasks'];
        } elseif (isset($a['set']) && ($set = self::set($c, $a['set']))) {
            $ids = self::setIds($c, $set);
        }
        $u = $c->universe();
        $ids = array_slice(array_values(array_filter($ids, static fn($id) => isset($u[$id]) && !$u[$id]['done'])), 0, 30);
        if (!$ids) {
            return self::out(['error' => 'no open tasks given (task ref, set or selected)'], [], 'suggest_deadline: بدون تسک');
        }
        $per = [];
        $all = [];
        foreach ($u as $t) {
            if ($t['done'] && ($fin = TaskAnalytics::finishedAt($t)) !== null) {
                $d = max(0.5, (strtotime($fin) - strtotime((string) $t['created_at'])) / 86400);
                $all[] = $d;
                $per[(int) $t['assignee_id']][] = $d;
            }
        }
        $out = [];
        $rows = [];
        foreach ($ids as $id) {
            $t = $u[$id];
            $aid = (int) $t['assignee_id'];
            $sample = count($per[$aid] ?? []) >= 3 ? $per[$aid] : $all;
            $med = TaskAnalytics::median($sample);
            if ($med === null) {
                $out[] = ['task' => $c->ref('t', $id), 'suggested' => null, 'why' => 'no history'];
                $rows[] = [['t' => 'task', 'id' => pid('tasks', $id), 'tok' => $t['share_token'], 'v' => $t['title']], 'سابقه‌ی کافی نیست', '—'];
                continue;
            }
            $date = Common::today()->modify('+' . max(1, (int) ceil($med)) . ' day')->format('Y-m-d');
            $out[] = ['task' => $c->ref('t', $id), 'suggested_date' => $date, 'basis' => count($per[$aid] ?? []) >= 3 ? 'assignee median' : 'project median', 'median_days' => round($med, 1)];
            $rows[] = [['t' => 'task', 'id' => pid('tasks', $id), 'tok' => $t['share_token'], 'v' => $t['title']], jdate($date . ' 00:00:00', false, true),
                (count($per[$aid] ?? []) >= 3 ? 'میانه‌ی مسئول' : 'میانه‌ی پروژه') . ' ' . fa_num(round($med, 1)) . ' روز'];
        }
        return self::out(['suggestions' => $out, 'note' => 'ESTIMATE from historical medians; apply only via preview_bulk_update items after user agrees'],
            [['type' => 'table', 'title' => 'پیشنهاد سررسید (تخمینی، از سرعت تاریخی)', 'cols' => ['تسک', 'سررسید پیشنهادی', 'مبنا'], 'rows' => $rows]], 'suggest_deadline (' . count($out) . ')');
    }

    // ───────────────────────── تکراری‌ها ─────────────────────────

    /** واژه‌های معنادار عنوان */
    public static function words(string $s): array
    {
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', Assistant::norm($s)) ?? '';
        $stop = ['و', 'در', 'به', 'از', 'با', 'برای', 'که', 'را', 'این', 'آن', 'یک', 'the', 'a', 'of', 'to'];
        return array_values(array_unique(array_filter(explode(' ', $s), static fn($w) => mb_strlen($w) > 1 && !in_array($w, $stop, true))));
    }

    public static function similarity(string $a, string $b): float
    {
        $x = self::words($a);
        $y = self::words($b);
        if (!$x || !$y) {
            return 0.0;
        }
        $j = count(array_intersect($x, $y)) / count(array_unique(array_merge($x, $y)));
        similar_text(implode(' ', $x), implode(' ', $y), $pct);
        return max($j, $pct / 100 * 0.9);
    }

    /** تسک‌های موجود شبیه یک عنوان (برای پیش‌نمایش ساخت تسک) */
    public static function similarTo(BoardContext $c, string $title, int $limit = 2): array
    {
        $hits = [];
        foreach ($c->universe() as $t) {
            if ($t['status'] === 'archived') {
                continue;
            }
            $s = self::similarity($title, (string) $t['title']);
            if ($s >= 0.62) {
                $hits[] = [$s, $t];
            }
        }
        usort($hits, static fn($x, $y) => $y[0] <=> $x[0]);
        return array_slice($hits, 0, $limit);
    }

    public static function duplicates(BoardContext $c, array $a): array
    {
        $rows = [];
        $data = [];
        $titles = array_values(array_filter((array) ($a['titles'] ?? []), 'is_string'));
        if ($titles) {
            foreach (array_slice($titles, 0, 20) as $ti) {
                foreach (self::similarTo($c, $ti, 3) as [$s, $t]) {
                    $data[] = ['title' => mb_substr($ti, 0, 120), 'similar' => $c->ref('t', (int) $t['id']), 'score' => round($s, 2)];
                    $rows[] = [mb_substr($ti, 0, 120), ['t' => 'task', 'id' => pid('tasks', (int) $t['id']), 'tok' => $t['share_token'], 'v' => $t['title']], fa_num((int) round($s * 100)) . '٪'];
                }
            }
        } else {
            $open = array_values(array_filter($c->universe(), static fn($t) => $t['status'] !== 'archived'));
            $open = array_slice($open, 0, 400);
            $n = count($open);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $s = self::similarity((string) $open[$i]['title'], (string) $open[$j]['title']);
                    if ($s >= 0.7) {
                        $data[] = ['a' => $c->ref('t', (int) $open[$i]['id']), 'b' => $c->ref('t', (int) $open[$j]['id']), 'score' => round($s, 2)];
                        $rows[] = [['t' => 'task', 'id' => pid('tasks', (int) $open[$i]['id']), 'tok' => $open[$i]['share_token'], 'v' => $open[$i]['title']],
                            ['t' => 'task', 'id' => pid('tasks', (int) $open[$j]['id']), 'tok' => $open[$j]['share_token'], 'v' => $open[$j]['title']], fa_num((int) round($s * 100)) . '٪'];
                        if (count($data) >= 30) {
                            break 2;
                        }
                    }
                }
            }
        }
        $blocks = [['type' => 'table', 'title' => 'تسک‌های مشابه/تکراری', 'cols' => $titles ? ['عنوان پیشنهادی', 'تسک موجود مشابه', 'شباهت'] : ['تسک', 'مشابه', 'شباهت'], 'rows' => $rows]];
        return self::out(['pairs' => $data, 'count' => count($data)], $blocks, 'find_duplicates (' . count($data) . ')');
    }

    // ───────────────────────── گزارش بازه‌ای ─────────────────────────

    public static function periodReport(BoardContext $c, array $a): array
    {
        $to = isset($a['to']) && is_string($a['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['to']) ? $a['to'] : Common::today()->format('Y-m-d');
        $from = isset($a['from']) && is_string($a['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['from']) ? $a['from'] : date('Y-m-d', strtotime($to . ' -6 days'));
        if ($from > $to || (strtotime($to) - strtotime($from)) > 400 * 86400) {
            return self::out(['error' => 'invalid range (max ~400 days)'], [], 'period_report: بازه‌ی نامعتبر');
        }
        $u = $c->universe();
        $in = static fn(?string $d) => $d !== null && substr($d, 0, 10) >= $from && substr($d, 0, 10) <= $to;
        $k = ['created' => 0, 'finished' => 0, 'finished_late' => 0, 'delivered' => 0, 'overdue_now' => 0, 'open_now' => 0];
        $per = [];
        $labels = [];
        $done = [];
        foreach ($u as $t) {
            $k['created'] += $in((string) $t['created_at']) ? 1 : 0;
            $k['delivered'] += $in($t['first_delivered_at']) ? 1 : 0;
            if (!$t['done']) {
                $k['open_now']++;
                $k['overdue_now'] += $t['overdue'] ? 1 : 0;
            }
            $fin = $t['done'] ? TaskAnalytics::finishedAt($t) : null;
            if ($in($fin)) {
                $late = TaskAnalytics::isLate($t, $fin);
                $k['finished']++;
                $k['finished_late'] += $late ? 1 : 0;
                $pk = (int) $t['assignee_id'];
                $per[$pk] ??= ['finished' => 0, 'late' => 0];
                $per[$pk]['finished']++;
                $per[$pk]['late'] += $late ? 1 : 0;
                $lk = (int) $t['subject_id'];
                $labels[$lk] = ($labels[$lk] ?? 0) + 1;
                $done[] = (int) $t['id'];
            }
        }
        uasort($per, static fn($x, $y) => $y['finished'] <=> $x['finished']);
        $range = jdate($from . ' 00:00:00', false) . ' تا ' . jdate($to . ' 00:00:00', false);
        $blocks = [
            ['type' => 'kpis', 'items' => [
                ['label' => 'ایجادشده', 'value' => fa_num($k['created'])], ['label' => 'انجام‌شده', 'value' => fa_num($k['finished'])],
                ['label' => 'انجام با تأخیر', 'value' => fa_num($k['finished_late']), 'tone' => $k['finished_late'] ? 'warn' : ''],
                ['label' => 'عقب‌افتاده‌ی فعلی', 'value' => fa_num($k['overdue_now']), 'tone' => $k['overdue_now'] ? 'danger' : ''],
            ]],
            ['type' => 'table', 'title' => 'انجام‌شده‌ها به تفکیک مسئول — ' . $range, 'cols' => ['مسئول', 'انجام‌شده', 'با تأخیر'],
                'rows' => array_values(array_map(static fn($pk, $v) => [self::user($c, $pk ?: null), fa_num($v['finished']), fa_num($v['late'])], array_keys($per), $per))],
        ];
        $setRef = null;
        if ($done) {
            [$set, $blk] = self::makeSet($c, 'تسک‌های انجام‌شده — ' . $range, $done, ['report' => [$from, $to]]);
            $blocks[] = $blk;
            $setRef = 'R' . $set['seq'];
        }
        return self::out(['from' => $from, 'to' => $to, 'kpis' => $k, 'finished_set' => $setRef,
            'by_assignee' => array_map(static fn($pk, $v) => ['member' => $pk ? $c->ref('u', $pk) . ' ' . $c->name($pk) : 'unassigned'] + $v, array_keys($per), $per),
            'by_label_finished' => array_map(static fn($lk, $n) => ['label' => $lk ? ($c->labels()[$lk][0] ?? '') : 'none', 'finished' => $n], array_keys($labels), $labels)],
            $blocks, 'period_report ' . $from . '..' . $to);
    }

    // ───────────────────────── پیشنهادهای برنامه‌ریزی ─────────────────────────

    /** کارهای مناسب امروز کاربر (قاعده‌ی ثابت: عقب‌افتاده، برگشتی، امروز، اولویت زیاد، نزدیک) */
    public static function suggestToday(BoardContext $c, array $a): array
    {
        $today = Common::today()->format('Y-m-d');
        $in2 = Common::today()->modify('+2 day')->format('Y-m-d');
        $mine = array_filter($c->universe(), fn($t) => !$t['done'] && (int) $t['assignee_id'] === $c->uid && $t['status'] !== 'review');
        $scored = [];
        foreach ($mine as $t) {
            $s = ($t['overdue'] ? 40 : 0) + ($t['status'] === 'rework' ? 30 : 0) + ($t['due_date'] === $today ? 25 : 0)
                + ($t['due_date'] !== null && $t['due_date'] > $today && $t['due_date'] <= $in2 ? 10 : 0) + ($t['priority'] === 'high' ? 15 : ($t['priority'] === 'medium' ? 5 : 0));
            $scored[] = [$s, $t];
        }
        usort($scored, static fn($x, $y) => [$y[0], $x[1]['due_date'] ?? '9999'] <=> [$x[0], $y[1]['due_date'] ?? '9999']);
        $pick = array_slice(array_column($scored, 1), 0, 8);
        [$set, $block] = self::makeSet($c, 'پیشنهاد کارهای امروز ' . $c->name($c->uid), array_map(static fn($t) => (int) $t['id'], $pick));
        return self::out(['result_set' => 'R' . $set['seq'], 'tasks' => array_map([$c, 'brief'], $pick), 'rule' => 'overdue > rework > due today > high priority > due in 2 days'],
            [$block], 'suggest_today → R' . $set['seq']);
    }

    /** پیشنهاد متعادل‌سازی بار کاری (همان الگوریتم صفحه‌ی «بار کاری»)؛ اجرا فقط با پیش‌نمایش و تأیید */
    public static function rebalance(BoardContext $c, array $a): array
    {
        if (!WorkloadService::canView($c->projectId, $c->uid)) {
            return self::out(['error' => 'no permission to view workload of this project'], [['type' => 'notes', 'title' => 'متعادل‌سازی بار کاری', 'items' => ['دسترسی «تحلیل پروژه» برای دیدن بار کاری اعضا را ندارید.']]], 'suggest_rebalance: بدون دسترسی');
        }
        $res = WorkloadService::suggestRebalance(WorkloadService::build($c->projectId, $c->uid));
        $out = [];
        $rows = [];
        foreach ($res['suggestions'] as $s) {
            $tid = Pid::id('tasks', (string) $s['task_id']);
            $from = Pid::id('users', (string) $s['from_id']);
            $to = Pid::id('users', (string) $s['to_id']);
            if ($tid === null || $c->task($tid) === null || $to === null) {
                continue;
            }
            $t = $c->task($tid);
            $out[] = ['task' => $c->ref('t', $tid), 'from' => $c->ref('u', (int) $from) . ' ' . $s['from_name'], 'to' => $c->ref('u', $to) . ' ' . $s['to_name'],
                'from_load' => $s['from_before'] . '%→' . $s['from_after'] . '%', 'to_load' => $s['to_before'] . '%→' . $s['to_after'] . '%', 'new_due_date' => $s['new_due_date']];
            $rows[] = [['t' => 'task', 'id' => pid('tasks', $tid), 'tok' => $t['share_token'], 'v' => $t['title']], self::user($c, (int) $from), self::user($c, $to),
                fa_num($s['from_before']) . '٪ ← ' . fa_num($s['from_after']) . '٪'];
        }
        return self::out(['suggestions' => $out, 'can_reassign' => WorkloadService::canManage($c->projectId, $c->uid),
            'note' => 'suggestions only; to apply call preview_bulk_update with items [{task, changes:{assignee}}] — the user must confirm'],
            [['type' => 'table', 'title' => 'پیشنهاد متعادل‌سازی بار کاری', 'cols' => ['تسک', 'از', 'به', 'بار مبدأ'], 'rows' => $rows,
                'note' => $rows ? 'فقط پیشنهاد است؛ برای اجرا بنویسید «همین پیشنهادها را اعمال کن» تا پیش‌نمایش ساخته شود.' : 'بار کاری متعادل است یا جابه‌جایی مناسبی پیدا نشد.']],
            'suggest_rebalance (' . count($out) . ')');
    }
}
