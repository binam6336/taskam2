<?php
declare(strict_types=1);

namespace KarlixLaravel\Services\Karlix;

use App\Core\Audit;
use KarlixLaravel\Database\DB;
use App\Core\Log;
use App\Core\Pid;
use App\Core\Validate;
use KarlixLaravel\Repositories\TaskModel;
use KarlixLaravel\Services\Access;
use KarlixLaravel\Services\BlockService;
use KarlixLaravel\Services\BoardService;
use KarlixLaravel\Services\Priority;
use KarlixLaravel\Services\TaskService;

/**
 * عملیات تغییردهنده‌ی دستیار بورد (v7.20): همیشه اول «پیش‌نمایش» (بدون هیچ تغییری)، بعد «تأیید و اجرا» فقط با کلیک کاربر
 * و توکن یک‌بارمصرف (هش در ai_actions، متصل به کاربر، گفتگو، همین تغییرات و زمان انقضا)، بعد امکان «بازگردانی».
 * اجرا فقط از سرویس‌های اصلی (TaskService::update، BoardService، Actions::run، TaskService::create، BlockService) با دسترسی همان کاربر
 * تا تاریخچه، اعلان‌ها، یادآورها و قواعد بورد مثل همیشه اجرا شوند. مدل هیچ راهی برای اجرای مستقیم ندارد.
 */
final class BoardOps
{
    /** دقیقه‌ی اعتبار توکن تأیید */
    public const CONFIRM_MINUTES = 15;
    /** ساعت مجاز برای بازگردانی */
    public const UNDO_HOURS = 24;
    public const CREATE_MAX = 20;

    private const PRIO = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

    private static function err(string $msg): array
    {
        return ['data' => ['error' => $msg], 'blocks' => [], 'summary' => 'preview: ' . $msg];
    }

    // ───────────────────────── پیش‌نمایش تغییر گروهی ─────────────────────────

    /** تغییرات مدل ⇐ مشخصات معتبر (یا خطا) */
    private static function changes(BoardContext $c, $raw): array
    {
        if (!is_array($raw)) {
            return [null, 'changes must be an object'];
        }
        $ch = [];
        $known = ['due_date', 'due_time', 'reminder_time', 'shift_days', 'clear_due', 'assignee', 'add_label', 'remove_label', 'priority', 'status', 'archive', 'title'];
        foreach (array_keys($raw) as $k) {
            if (!in_array($k, $known, true)) {
                return [null, 'unknown change "' . mb_substr((string) $k, 0, 30) . '"; allowed: ' . implode(', ', $known)];
            }
        }
        $today = Common::today();
        if (isset($raw['due_date'])) {
            $d = is_string($raw['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw['due_date']) ? Validate::date($raw['due_date']) : null;
            if ($d === null) {
                return [null, 'due_date must be YYYY-MM-DD (Gregorian, from CALENDAR)'];
            }
            $diff = (int) $today->diff(new \DateTimeImmutable($d, new \DateTimeZone('Asia/Tehran')))->format('%r%a');
            if ($diff < -366 || $diff > 3660) {
                return [null, 'due_date out of range'];
            }
            $ch['due_date'] = $d;
        }
        $time = $raw['due_time'] ?? $raw['reminder_time'] ?? null;
        if ($time !== null) {
            $t = is_string($time) && preg_match('/^\d{1,2}:\d{2}$/', trim($time)) ? Validate::time(str_pad(trim($time), 5, '0', STR_PAD_LEFT)) : null;
            if ($t === null) {
                return [null, 'due_time must be HH:MM (24h)'];
            }
            $ch['due_time'] = substr($t, 0, 5);
        }
        if (isset($raw['shift_days'])) {
            if (!is_numeric($raw['shift_days']) || abs((int) $raw['shift_days']) > 365 || (int) $raw['shift_days'] === 0 || isset($ch['due_date'])) {
                return [null, 'shift_days must be a non-zero integer (-365..365) and not combined with due_date'];
            }
            $ch['shift_days'] = (int) $raw['shift_days'];
        }
        if (!empty($raw['clear_due'])) {
            if (isset($ch['due_date']) || isset($ch['shift_days']) || isset($ch['due_time'])) {
                return [null, 'clear_due cannot be combined with other due changes'];
            }
            $ch['clear_due'] = true;
        }
        if (isset($raw['assignee'])) {
            $id = $raw['assignee'] === 'me' ? $c->uid : $c->idOf('u', $raw['assignee']);
            if ($id === null || !isset($c->members()[$id])) {
                return [null, 'assignee must be a U ref from MEMBERS (or "me")'];
            }
            $ch['assignee'] = $id;
        }
        if (isset($raw['add_label'])) {
            $id = $c->idOf('l', $raw['add_label']);
            if ($id === null || !isset($c->labels()[$id])) {
                return [null, 'add_label must be an L ref from LABELS (existing labels only)'];
            }
            $ch['label'] = $id;
        } elseif (!empty($raw['remove_label'])) {
            $ch['label'] = 0;
        }
        if (isset($raw['priority'])) {
            $p = $raw['priority'];
            if (is_string($p) && isset(self::PRIO[$p])) {
                $ch['priority'] = $p;
            } elseif (is_numeric($p) && Priority::percent($p) !== null) {
                $ch['priority_percent'] = Priority::percent($p);
            } else {
                return [null, 'priority must be low|medium|high or 0..100'];
            }
        }
        if (isset($raw['status'])) {
            $s = is_string($raw['status']) ? trim($raw['status']) : '';
            $s = $s === 'complete' ? 'done' : $s;
            if (!isset($c->columns()[$s])) {
                return [null, 'status must be a column key from COLUMNS'];
            }
            $ch['status'] = $s;
        }
        if (isset($raw['title'])) {
            $ti = is_string($raw['title']) ? trim(preg_replace('/\s+/u', ' ', $raw['title']) ?? '') : '';
            if ($ti === '' || mb_strlen($ti) > 255) {
                return [null, 'title must be 1..255 characters'];
            }
            $ch['title'] = $ti;
        }
        if (!empty($raw['archive'])) {
            if (isset($ch['status'])) {
                return [null, 'archive cannot be combined with status'];
            }
            $ch['archive'] = true;
        }
        return $ch ? [$ch, null] : [null, 'no changes given'];
    }

    /** وضعیت مقصد واقعی و مجاز بودن (همان قواعد BoardService) */
    private static function statusPlan(BoardContext $c, array $t, string $to): array
    {
        $from = $t['status'];
        if ($from === 'archived') {
            return [false, null, 'تسک آرشیو شده است.'];
        }
        if ($from === $to) {
            return [true, null, null];
        }
        if ($to === 'done' && $from !== 'review') {
            if (!Access::canDeliver($t, $c->uid)) {
                return [false, null, 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را تحویل دهد.'];
            }
            return Access::canApprove($t, $c->uid) ? [true, 'done', null] : [true, 'review', 'دسترسی تکمیل ندارید؛ به «' . ($c->columns()['review'] ?? 'بررسی سرپرست') . '» می‌رود.'];
        }
        if ($to === 'done' && !Access::canTask($t, $c->uid, 'complete')) {
            return [false, null, 'این تسک منتظر تأیید سرپرست است و دسترسی تأیید و تکمیل آن را ندارید.'];
        }
        if (!in_array($to, Access::boardTargets($t, $c->uid, $from, array_keys($c->columns())), true)) {
            return [false, null, BoardService::lockReason($t, $c->uid, $from, $to)];
        }
        return [true, $to, null];
    }

    private static function fmtDue(?string $d, ?string $tm): string
    {
        if ($d === null) {
            return 'بدون سررسید';
        }
        return jdate($d . ' 00:00:00', false, true) . ($tm ? ' ساعت ' . fa_num(substr($tm, 0, 5)) : '');
    }

    /** یک تسک: [ok, changes[], reason|null, before, after] */
    private static function planTask(BoardContext $c, array $t, array $ch): array
    {
        $uid = $c->uid;
        $cols = $c->columns();
        $before = ['due_date' => $t['due_date'], 'due_time' => $t['due_time'] !== null ? substr((string) $t['due_time'], 0, 5) : null,
            'assignee_id' => $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null, 'subject_id' => $t['subject_id'] !== null ? (int) $t['subject_id'] : null,
            'priority' => (string) $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null, 'status' => $t['status'],
            'title' => (string) $t['title']];
        $after = [];
        $view = [];
        if (isset($ch['title']) && $ch['title'] !== $before['title']) {
            if (!Access::canTask($t, $uid, 'edit_info')) {
                return [false, [], 'اجازه‌ی ویرایش عنوان این تسک را ندارید.', $before, []];
            }
            $after['title'] = $ch['title'];
            $view[] = ['f' => 'عنوان', 'from' => mb_substr($before['title'], 0, 80), 'to' => mb_substr($ch['title'], 0, 80)];
        }
        // سررسید
        if (isset($ch['due_date']) || isset($ch['due_time']) || isset($ch['shift_days']) || isset($ch['clear_due'])) {
            if (!Access::canTask($t, $uid, 'edit_due')) {
                return [false, [], 'اجازه‌ی تغییر زمان انجام این تسک را ندارید.', $before, []];
            }
            $d = $before['due_date'];
            $tm = $before['due_time'];
            if (!empty($ch['clear_due'])) {
                $d = $tm = null;
            } else {
                if (isset($ch['shift_days'])) {
                    if ($d === null) {
                        return [false, [], 'سررسید ندارد که جابه‌جا شود.', $before, []];
                    }
                    $d = (new \DateTimeImmutable($d))->modify(($ch['shift_days'] > 0 ? '+' : '') . $ch['shift_days'] . ' day')->format('Y-m-d');
                }
                if (isset($ch['due_date'])) {
                    $d = $ch['due_date'];
                }
                if (isset($ch['due_time'])) {
                    if ($d === null) {
                        return [false, [], 'سررسید ندارد؛ یادآوری (ساعت) بدون تاریخ تنظیم نمی‌شود.', $before, []];
                    }
                    $tm = $ch['due_time'];
                }
            }
            if ($d !== $before['due_date'] || $tm !== $before['due_time']) {
                $after['due_date'] = $d;
                $after['due_time'] = $tm;
                $view[] = ['f' => 'سررسید', 'from' => self::fmtDue($before['due_date'], $before['due_time']), 'to' => self::fmtDue($d, $tm)];
            }
        }
        if (isset($ch['assignee']) && $ch['assignee'] !== $before['assignee_id']) {
            if (!Access::canTask($t, $uid, 'reassign')) {
                return [false, [], 'اجازه‌ی تغییر مسئول این تسک را ندارید.', $before, []];
            }
            if ($ch['assignee'] !== $uid && Access::isBlockedBetween($uid, $ch['assignee'])) {
                return [false, [], 'بین شما و این کاربر مسدودی وجود دارد.', $before, []];
            }
            $after['assignee_id'] = $ch['assignee'];
            $view[] = ['f' => 'مسئول', 'from' => $before['assignee_id'] !== null ? (string) $c->name($before['assignee_id']) : 'بدون مسئول', 'to' => (string) $c->name($ch['assignee'])];
        }
        $meta = array_key_exists('label', $ch) || isset($ch['priority']) || isset($ch['priority_percent']);
        if ($meta && !Access::canTask($t, $uid, 'edit_meta')) {
            return [false, [], 'تغییر اولویت و لیبل فقط برای سازنده‌ی تسک یا مدیر پروژه ممکن است.', $before, []];
        }
        if (array_key_exists('label', $ch)) {
            $new = $ch['label'] ?: null;
            if ($new !== $before['subject_id']) {
                $after['subject_id'] = $new;
                $view[] = ['f' => 'لیبل', 'from' => $before['subject_id'] !== null ? (string) ($t['label_title'] ?? '') : 'بدون لیبل', 'to' => $new !== null ? $c->labels()[$new][0] : 'بدون لیبل'];
            }
        }
        if (isset($ch['priority']) && $ch['priority'] !== $before['priority']) {
            $after['priority'] = $ch['priority'];
            $view[] = ['f' => 'اولویت', 'from' => self::PRIO[$before['priority']] ?? $before['priority'], 'to' => self::PRIO[$ch['priority']]];
        }
        if (isset($ch['priority_percent']) && $ch['priority_percent'] !== $before['priority_percent']) {
            $after['priority_percent'] = $ch['priority_percent'];
            $view[] = ['f' => 'اولویت', 'from' => $before['priority_percent'] !== null ? fa_num($before['priority_percent']) . '٪' : '—', 'to' => fa_num($ch['priority_percent']) . '٪'];
        }
        if (isset($ch['status'])) {
            [$ok, $to, $note] = self::statusPlan($c, $t, $ch['status']);
            if (!$ok) {
                return [false, [], $note, $before, []];
            }
            if ($to !== null) {
                $after['status'] = $to;
                $view[] = ['f' => 'وضعیت', 'from' => $cols[$before['status']] ?? $before['status'], 'to' => $cols[$to] ?? $to, 'note' => $note];
            }
        }
        if (!empty($ch['archive'])) {
            if ($t['status'] === 'archived') {
                // بدون تغییر
            } elseif ($t['status'] !== 'done') {
                return [false, [], 'فقط تسک‌های انجام‌شده آرشیو می‌شوند.', $before, []];
            } elseif (!BoardService::canArchive($t, $uid)) {
                return [false, [], 'اجازه‌ی آرشیو این تسک را ندارید.', $before, []];
            } else {
                $after['status'] = 'archived';
                $view[] = ['f' => 'وضعیت', 'from' => $cols['done'] ?? 'انجام‌شده', 'to' => 'آرشیو'];
            }
        }
        return [true, $view, null, $before, $after];
    }

    /** ابزار preview_bulk_update */
    public static function previewBulk(BoardContext $c, array $a): array
    {
        $u = $c->universe();
        $items = [];
        $srcNote = '';
        if (isset($a['items']) && is_array($a['items'])) {
            foreach (array_slice($a['items'], 0, TaskService::BULK_MAX + 1) as $it) {
                $id = is_array($it) ? $c->idOf('t', $it['task'] ?? null) : null;
                if ($id === null) {
                    return self::err('every item needs a valid T ref in "task"');
                }
                [$ch, $e] = self::changes($c, $it['changes'] ?? null);
                if ($ch === null) {
                    return self::err($e);
                }
                $items[$id] = $ch;
            }
            $srcNote = 'تسک‌های پیشنهادی';
        } else {
            [$ch, $e] = self::changes($c, $a['changes'] ?? null);
            if ($ch === null) {
                return self::err($e);
            }
            $tg = is_array($a['target'] ?? null) ? $a['target'] : $a;
            $ids = [];
            if (!empty($tg['selected'])) {
                $ids = $c->selection['tasks'];
                $srcNote = 'تسک‌های انتخاب‌شده';
                if (!$ids) {
                    return self::err('user has not selected any task');
                }
            } elseif (isset($tg['set'])) {
                $set = BoardTools::set($c, $tg['set']);
                if ($set === null) {
                    return self::err('unknown result set (use R refs)');
                }
                $ids = BoardTools::setIds($c, $set);
                $srcNote = 'لیست R' . $set['seq'] . ' («' . $set['title'] . '»)';
            } elseif (isset($tg['tasks']) && is_array($tg['tasks'])) {
                foreach ($tg['tasks'] as $r) {
                    $id = $c->idOf('t', $r);
                    if ($id === null) {
                        return self::err('unknown task ref ' . mb_substr((string) (is_string($r) ? $r : ''), 0, 12));
                    }
                    $ids[] = $id;
                }
                $srcNote = 'تسک‌های نام‌برده';
            } else {
                return self::err('target required: {"set":"R1"} or {"selected":true} or {"tasks":["T1"]}');
            }
            foreach ($ids as $id) {
                $items[$id] = $ch;
            }
        }
        if (count($items) > TaskService::BULK_MAX) {
            return ['data' => ['error' => 'too many tasks (max ' . TaskService::BULK_MAX . ')'],
                'blocks' => [['type' => 'notes', 'title' => 'تعداد زیاد', 'items' => ['هر عملیات گروهی حداکثر ' . fa_num(TaskService::BULK_MAX) . ' تسک را تغییر می‌دهد؛ لیست را محدودتر کنید.']]], 'summary' => 'preview: بیش از سقف'];
        }
        $rows = $bad = [];
        $same = 0;
        $payload = [];
        foreach ($items as $id => $ch) {
            $t = $u[$id] ?? null;
            if ($t === null) {
                $bad[] = ['title' => 'تسک خارج از دسترس', 'reason' => 'این تسک در بورد شما دیده نمی‌شود.'];
                continue;
            }
            [$ok, $view, $reason, $before, $after] = self::planTask($c, $t, $ch);
            $link = ['t' => 'task', 'id' => pid('tasks', $id), 'tok' => $t['share_token'], 'v' => (string) $t['title']];
            if (!$ok) {
                $bad[] = ['task' => $link, 'reason' => $reason];
            } elseif (!$after) {
                $same++;
            } else {
                $rows[] = ['task' => $link, 'changes' => $view];
                $deliver = isset($after['status'], $ch['status']) && $ch['status'] === 'done' && $before['status'] !== 'review';
                $payload[] = ['id' => $id, 'before' => array_intersect_key($before, $after) + ['status' => $before['status']], 'after' => $after, 'deliver' => $deliver];
            }
        }
        if (!$payload) {
            $blk = ['type' => 'preview', 'kind' => 'bulk_update', 'action' => null, 'count' => 0, 'rows' => [], 'bad' => $bad, 'same' => $same, 'source' => $srcNote, 'status' => 'empty'];
            return ['data' => ['affected' => 0, 'not_allowed' => count($bad), 'unchanged' => $same, 'note' => 'nothing to apply'], 'blocks' => [$blk], 'summary' => 'preview_bulk_update: بدون تغییر قابل اجرا'];
        }
        [$action, $token] = self::store($c, 'bulk_update', ['tasks' => $payload, 'source' => $srcNote]);
        $blk = ['type' => 'preview', 'kind' => 'bulk_update', 'action' => $action['pid'], 'token' => $token, 'expires' => $action['expires_at'],
            'count' => count($payload), 'rows' => $rows, 'bad' => $bad, 'same' => $same, 'source' => $srcNote, 'status' => 'preview'];
        return ['data' => ['affected' => count($payload), 'not_allowed' => count($bad), 'unchanged' => $same, 'status' => 'waiting for the user to press confirm; do not claim it is done'],
            'blocks' => [$blk], 'summary' => 'preview_bulk_update (' . count($payload) . ' تسک، ' . count($bad) . ' غیرمجاز)'];
    }

    // ───────────────────────── پیش‌نمایش ساخت تسک از متن ─────────────────────────

    public static function previewCreate(BoardContext $c, array $a): array
    {
        $raw = is_array($a['tasks'] ?? null) ? $a['tasks'] : (is_array($a['items'] ?? null) ? $a['items'] : []);
        if (!$raw) {
            return self::err('tasks[] required');
        }
        $canOthers = Access::canAssignOnCreate($c->projectId, $c->uid);
        $items = [];
        foreach (array_slice($raw, 0, self::CREATE_MAX) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $title = Actions::text($it['title'] ?? null, 200);
            if ($title === '') {
                continue;
            }
            $lid = isset($it['label']) ? $c->idOf('l', $it['label']) : null;
            $aid = isset($it['assignee']) ? ($it['assignee'] === 'me' ? $c->uid : $c->idOf('u', $it['assignee'])) : null;
            if ($aid !== null && (!isset($c->members()[$aid]) || ($aid !== $c->uid && !$canOthers))) {
                $aid = null;
            }
            $est = isset($it['estimate_hours']) && is_numeric($it['estimate_hours']) ? max(0.25, min(400, round((float) $it['estimate_hours'], 2))) : null;
            $due = isset($it['due_date']) && is_string($it['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $it['due_date']) ? Validate::date($it['due_date']) : null;
            $dups = array_map(static fn($h) => ['t' => 'task', 'id' => pid('tasks', (int) $h[1]['id']), 'tok' => $h[1]['share_token'], 'v' => (string) $h[1]['title'], 'score' => (int) round($h[0] * 100)], BoardTools::similarTo($c, $title));
            $items[] = ['title' => $title, 'description' => Actions::text($it['description'] ?? null, 2000, true), 'label' => $lid !== null ? pid('subjects', $lid) : '',
                'assignee' => $aid !== null ? pid('users', $aid) : '', 'estimate' => $est, 'phase' => Actions::text($it['phase'] ?? null, 60), 'due' => $due, 'dups' => $dups];
        }
        if (!$items) {
            return self::err('no valid task titles');
        }
        $members = [];
        foreach ($c->members() as $id => $n) {
            if ($id === $c->uid || $canOthers) {
                $members[] = ['id' => pid('users', $id), 'name' => $n];
            }
        }
        $labels = array_map(static fn($id, $l) => ['id' => pid('subjects', $id), 'title' => $l[0]], array_keys($c->labels()), $c->labels());
        [$action, $token] = self::store($c, 'create_tasks', ['items' => $items]);
        $blk = ['type' => 'create_preview', 'action' => $action['pid'], 'token' => $token, 'expires' => $action['expires_at'], 'items' => $items,
            'labels' => array_values($labels), 'members' => $members, 'status' => 'preview'];
        $dupN = count(array_filter($items, static fn($i) => $i['dups']));
        return ['data' => ['proposed' => count($items), 'with_possible_duplicates' => $dupN, 'status' => 'editable preview shown; waiting for the user to confirm'],
            'blocks' => [$blk], 'summary' => 'preview_create_tasks (' . count($items) . ' تسک، ' . $dupN . ' مشابه)'];
    }

    /** ذخیره‌ی پیش‌نمایش + توکن تأیید (خود توکن فقط یک بار به رابط می‌رود) */
    private static function store(BoardContext $c, string $kind, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $token = bin2hex(random_bytes(24));
        $pid = Pid::new();
        $exp = date('Y-m-d H:i:s', time() + self::CONFIRM_MINUTES * 60);
        DB::insert('ai_actions', ['pid' => $pid, 'session_id' => (int) $c->session['id'], 'user_id' => $c->uid, 'real_user_id' => $c->realUid,
            'project_id' => $c->projectId, 'kind' => $kind, 'status' => 'preview', 'token_hash' => hash('sha256', $token),
            'payload' => $json, 'payload_hash' => hash('sha256', $json), 'expires_at' => $exp]);
        return [['pid' => $pid, 'expires_at' => $exp], $token];
    }

    /** توکن تازه برای پیش‌نمایشی که هنوز معتبر است (بعد از بارگذاری دوباره‌ی گفتگو؛ توکن قبلی باطل می‌شود) */
    public static function reissue(int $actionId): ?string
    {
        $token = bin2hex(random_bytes(24));
        return DB::run("UPDATE ai_actions SET token_hash = ? WHERE id = ? AND status = 'preview' AND expires_at > NOW()", [hash('sha256', $token), $actionId]) === 1 ? $token : null;
    }

    // ───────────────────────── تأیید و اجرا ─────────────────────────

    private static function res(bool $ok, string $msg, string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** پیدا کردن عملیات کاربر در همین گفتگو */
    public static function find(BoardContext $c, string $pid): ?array
    {
        return Pid::valid($pid) ? DB::one('SELECT * FROM ai_actions WHERE pid = ? AND session_id = ? AND user_id = ?', [$pid, (int) $c->session['id'], $c->uid]) : null;
    }

    /** $edits فقط برای create_tasks: ویرایش کاربر در پیش‌نمایش (همه دوباره اعتبارسنجی می‌شوند) */
    public static function apply(BoardContext $c, string $pid, string $token, array $edits = []): array
    {
        $act = self::find($c, $pid);
        if ($act === null) {
            return self::res(false, 'این پیش‌نمایش پیدا نشد.', 'AI_ACTION_NOT_FOUND', 404);
        }
        if ((int) ($act['real_user_id'] ?? 0) !== (int) ($c->realUid ?? 0)) {
            return self::res(false, 'این پیش‌نمایش در پنجره‌ی دیگری ساخته شده است؛ دوباره درخواست دهید.', 'AI_ACTION_SESSION', 409);
        }
        if ($act['status'] !== 'preview') {
            return self::res(false, $act['status'] === 'applied' || $act['status'] === 'undone' ? 'این عملیات قبلاً اجرا شده است.' : 'این پیش‌نمایش دیگر معتبر نیست.', 'AI_ACTION_USED', 409);
        }
        if (strtotime((string) $act['expires_at']) < time()) {
            DB::run("UPDATE ai_actions SET status = 'expired', token_hash = NULL WHERE id = ? AND status = 'preview'", [(int) $act['id']]);
            return self::res(false, 'زمان تأیید این پیش‌نمایش (' . fa_num(self::CONFIRM_MINUTES) . ' دقیقه) گذشته است؛ دوباره درخواست دهید.', 'AI_ACTION_EXPIRED', 409);
        }
        if (!preg_match('/^[0-9a-f]{48}$/', $token) || !hash_equals((string) $act['token_hash'], hash('sha256', $token)) || !hash_equals((string) $act['payload_hash'], hash('sha256', (string) $act['payload']))) {
            return self::res(false, 'توکن تأیید نامعتبر است.', 'AI_TOKEN_INVALID', 403);
        }
        // مصرف یک‌باره‌ی توکن (اتمی)
        if (DB::run("UPDATE ai_actions SET status = 'applying', token_hash = NULL WHERE id = ? AND status = 'preview' AND token_hash = ?", [(int) $act['id'], hash('sha256', $token)]) !== 1) {
            return self::res(false, 'این عملیات قبلاً اجرا شده است.', 'AI_ACTION_USED', 409);
        }
        @set_time_limit(180);
        $payload = json_decode((string) $act['payload'], true) ?: [];
        try {
            $out = $act['kind'] === 'create_tasks' ? self::runCreate($c, $payload, $edits) : self::runBulk($c, $payload);
        } catch (\Throwable $e) {
            Log::error($e, 'karlix', ['action' => (int) $act['id']]);
            $out = ['results' => [], 'undo' => [], 'ok' => 0, 'fail' => 0, 'error' => 'exception'];
        }
        $c->refresh();
        DB::run("UPDATE ai_actions SET status = 'applied', applied_at = NOW(), result = ?, `undo` = ? WHERE id = ?",
            [json_encode($out['results'], JSON_UNESCAPED_UNICODE), json_encode($out['undo']), (int) $act['id']]);
        Audit::log($c->uid, 'ai.board.apply', ['action' => $pid, 'kind' => $act['kind'], 'project' => $c->projectId, 'ok' => $out['ok'], 'fail' => $out['fail']]
            + ($c->realUid ? ['real_user_id' => $c->realUid] : []));
        $blk = self::resultBlock($pid, $act['kind'], $out['results'], $out['ok'], $out['fail'], (bool) $out['undo']);
        BoardAgent::note($c, 'نتیجه‌ی اجرای ' . ($act['kind'] === 'create_tasks' ? 'ساخت تسک‌ها' : 'تغییر گروهی') . ': ' . fa_num($out['ok']) . ' موفق، ' . fa_num($out['fail']) . ' ناموفق.', [$blk], ['apply' => $pid]);
        return self::res(true, '', '', 200, ['block' => $blk]);
    }

    private static function resultBlock(string $pid, string $kind, array $results, int $ok, int $fail, bool $canUndo, string $state = 'applied'): array
    {
        return ['type' => 'result', 'action' => $pid, 'kind' => $kind, 'ok' => $ok, 'fail' => $fail, 'state' => $state,
            'errors' => array_values(array_filter($results, static fn($r) => !$r['ok'])), 'done' => array_values(array_filter($results, static fn($r) => $r['ok'])),
            'can_undo' => $canUndo && $ok > 0 && $state === 'applied'];
    }

    /** مقدار فعلی ردیف تسک برای مقایسه */
    private static function current(array $t, BoardContext $c): array
    {
        return ['due_date' => $t['due_date'], 'due_time' => $t['due_time'] !== null ? substr((string) $t['due_time'], 0, 5) : null,
            'assignee_id' => $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null, 'subject_id' => $t['subject_id'] !== null ? (int) $t['subject_id'] : null,
            'priority' => (string) $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null,
            'status' => BoardService::statusOf($t), 'title' => (string) $t['title']];
    }

    private static function runBulk(BoardContext $c, array $payload): array
    {
        $results = $undo = [];
        $ok = $fail = 0;
        foreach ($payload['tasks'] ?? [] as $p) {
            $id = (int) $p['id'];
            $c->refresh();
            $t = $c->task($id);
            $title = $t !== null ? (string) $t['title'] : (string) DB::value('SELECT title FROM tasks WHERE id = ?', [$id]);
            $link = ['t' => 'task', 'id' => pid('tasks', $id), 'tok' => $t['share_token'] ?? '', 'v' => $title];
            if ($t === null) {
                $results[] = ['task' => $link, 'ok' => false, 'text' => 'دیگر در بورد شما نیست یا مسدود شده است.'];
                $fail++;
                continue;
            }
            $cur = self::current($t, $c);
            // اگر بعد از پیش‌نمایش کسی همین فیلدها را عوض کرده، تغییر قدیمی روی داده‌ی جدید اعمال نمی‌شود
            foreach ($p['before'] as $k => $v) {
                if (array_key_exists($k, $p['after']) && (string) $cur[$k] !== (string) $v) {
                    $results[] = ['task' => $link, 'ok' => false, 'text' => 'بعد از پیش‌نمایش تغییر کرده است؛ برای اطمینان اجرا نشد.'];
                    $fail++;
                    continue 2;
                }
            }
            $after = $p['after'];
            $in = [];
            if (array_key_exists('due_date', $after)) {
                $in['due_date'] = $after['due_date'] ?? '';
                $in['due_time'] = $after['due_time'] ?? '';
            }
            if (array_key_exists('assignee_id', $after)) {
                $in['assignee_id'] = $after['assignee_id'];
            }
            if (array_key_exists('subject_id', $after)) {
                $in['subject_id'] = $after['subject_id'] ?? '';
            }
            if (array_key_exists('title', $after)) {
                $in['title'] = $after['title'];
            }
            if (array_key_exists('priority_percent', $after)) {
                $in['priority_percent'] = $after['priority_percent'];
            } elseif (array_key_exists('priority', $after)) {
                $in['priority'] = $after['priority'];
            }
            $notes = [];
            $done = [];
            if ($in) {
                $r = TaskService::update($c->uid, $id, $in, 'karlix');
                if (!$r['ok']) {
                    $results[] = ['task' => $link, 'ok' => false, 'text' => $r['message']];
                    $fail++;
                    continue;
                }
                $done = array_keys(array_diff_key($after, ['status' => 1]));
            }
            if (isset($after['status'])) {
                if ($after['status'] === 'archived') {
                    $r = BoardService::move($c->uid, $id, 'archived');
                    $sok = $r['ok'];
                    $stext = $r['message'];
                } else {
                    // «انجام‌شده» از غیر بررسی = تیک تحویل (BoardService::deliver)؛ بقیه = انتقال عادی بورد
                    $r = Actions::run(['type' => 'change_status', 'status' => !empty($p['deliver']) ? 'done' : $after['status']], $c->uid, $id);
                    $sok = $r['ok'];
                    $stext = $r['text'];
                }
                if ($sok) {
                    $done[] = 'status';
                } else {
                    $notes[] = 'وضعیت: ' . $stext;
                }
            }
            $fresh = TaskModel::raw($id);
            $now = $fresh ? self::current($fresh, $c) : [];
            $applied = array_intersect_key($now, array_flip($done));
            if ($done) {
                $undo[] = ['id' => $id, 'before' => array_intersect_key($p['before'] + $cur, $applied), 'after' => $applied];
            }
            $full = !$notes;
            $results[] = ['task' => $link, 'ok' => (bool) $done, 'partial' => $done && !$full, 'text' => $full ? 'انجام شد.' : implode(' ', $notes)];
            $done ? $ok++ : $fail++;
        }
        return ['results' => $results, 'undo' => $undo, 'ok' => $ok, 'fail' => $fail];
    }

    private static function runCreate(BoardContext $c, array $payload, array $edits): array
    {
        $results = $undo = [];
        $ok = $fail = 0;
        $labels = $c->labels();
        $members = $c->members();
        foreach ($payload['items'] ?? [] as $i => $it) {
            $e = is_array($edits[$i] ?? null) ? $edits[$i] : [];
            if (array_key_exists('include', $e) && !$e['include']) {
                continue;
            }
            $title = Actions::text($e['title'] ?? $it['title'], 255);
            $desc = Actions::text($e['description'] ?? $it['description'], 3000, true);
            $lp = (string) ($e['label'] ?? $it['label']);
            $ap = (string) ($e['assignee'] ?? $it['assignee']);
            $lid = $lp !== '' ? Pid::id('subjects', $lp) : null;
            $aid = $ap !== '' ? Pid::id('users', $ap) : null;
            $est = isset($e['estimate']) ? (is_numeric($e['estimate']) ? max(0.25, min(400, round((float) $e['estimate'], 2))) : null) : $it['estimate'];
            $phase = Actions::text($e['phase'] ?? $it['phase'], 60);
            $due = isset($e['due']) ? (is_string($e['due']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['due']) ? Validate::date($e['due']) : null) : $it['due'];
            $res = ['title' => $title];
            if ($title === '' || ($lid !== null && !isset($labels[$lid])) || ($aid !== null && !isset($members[$aid]))) {
                $results[] = $res + ['ok' => false, 'text' => $title === '' ? 'عنوان خالی است.' : 'لیبل یا مسئول انتخاب‌شده در این پروژه نیست.'];
                $fail++;
                continue;
            }
            $extra = trim(($phase !== '' ? 'فاز: ' . $phase : '') . ($est !== null ? ($phase !== '' ? ' — ' : '') . 'تخمین: ' . fa_num($est) . ' ساعت' : ''));
            $r = TaskService::create($c->uid, ['title' => $title, 'description' => trim($desc . ($extra !== '' ? "\n\n" . $extra : '')), 'project_id' => $c->projectId,
                'subject_id' => $lid ?? '', 'assignee_id' => $aid ?? $c->uid, 'due_date' => $due ?? '']);
            if ($r['ok']) {
                $tid = (int) $r['data']['id'];
                $undo[] = ['id' => $tid, 'created' => true];
                $results[] = $res + ['ok' => true, 'text' => 'ساخته شد.', 'task' => ['t' => 'task', 'id' => pid('tasks', $tid), 'tok' => (string) ($r['data']['share_token'] ?? ''), 'v' => $title]];
                $ok++;
            } else {
                $results[] = $res + ['ok' => false, 'text' => $r['message']];
                $fail++;
            }
        }
        return ['results' => $results, 'undo' => $undo, 'ok' => $ok, 'fail' => $fail];
    }

    // ───────────────────────── بازگردانی ─────────────────────────

    public static function undo(BoardContext $c, string $pid): array
    {
        $act = self::find($c, $pid);
        if ($act === null) {
            return self::res(false, 'این عملیات پیدا نشد.', 'AI_ACTION_NOT_FOUND', 404);
        }
        if ($act['status'] !== 'applied') {
            return self::res(false, $act['status'] === 'undone' ? 'این عملیات قبلاً بازگردانی شده است.' : 'این عملیات قابل بازگردانی نیست.', 'AI_UNDO_INVALID', 409);
        }
        if (strtotime((string) $act['applied_at']) < time() - self::UNDO_HOURS * 3600) {
            return self::res(false, 'مهلت بازگردانی (' . fa_num(self::UNDO_HOURS) . ' ساعت) گذشته است.', 'AI_UNDO_EXPIRED', 409);
        }
        if (DB::run("UPDATE ai_actions SET status = 'undoing' WHERE id = ? AND status = 'applied'", [(int) $act['id']]) !== 1) {
            return self::res(false, 'این عملیات قبلاً بازگردانی شده است.', 'AI_UNDO_INVALID', 409);
        }
        $results = [];
        $ok = $fail = 0;
        foreach (json_decode((string) $act["undo"], true) ?: [] as $u) {
            $id = (int) $u['id'];
            $raw = TaskModel::raw($id);
            $link = ['t' => 'task', 'id' => pid('tasks', $id), 'tok' => (string) ($raw['share_token'] ?? ''), 'v' => (string) ($raw['title'] ?? 'تسک')];
            if ($raw === null) {
                $results[] = ['task' => $link, 'ok' => false, 'text' => 'تسک دیگر وجود ندارد.'];
                $fail++;
                continue;
            }
            if (!empty($u['created'])) {
                $r = !empty($raw['blocked_at']) ? ['ok' => true, 'message' => ''] : BlockService::block('task', $c->uid, $id);
                $results[] = ['task' => $link, 'ok' => $r['ok'], 'text' => $r['ok'] ? 'تسک ساخته‌شده مسدود شد (کاربران چیزی را حذف نمی‌کنند).' : $r['message']];
                $r['ok'] ? $ok++ : $fail++;
                continue;
            }
            $cur = self::current($raw, $c);
            $errs = [];
            $in = [];
            foreach ($u['after'] as $k => $v) {
                if ($k === 'status') {
                    continue;
                }
                if ((string) $cur[$k] !== (string) $v) {
                    $errs[] = 'بعد از اجرا دوباره تغییر کرده است.';
                    continue;
                }
                $b = $u['before'][$k] ?? null;
                if ($k === 'due_date') {
                    $in['due_date'] = $b ?? '';
                    $in['due_time'] = $u['before']['due_time'] ?? '';
                } elseif ($k === 'due_time') {
                    $in['due_time'] = $b ?? '';
                } elseif ($k === 'subject_id') {
                    $in['subject_id'] = $b ?? '';
                } else {
                    $in[$k] = $b;
                }
            }
            if ($in) {
                $r = TaskService::update($c->uid, $id, $in, 'karlix');
                if (!$r['ok']) {
                    $errs[] = $r['message'];
                }
            }
            if (isset($u['after']['status'])) {
                $to = (string) ($u['before']['status'] ?? '');
                if ($cur['status'] !== $u['after']['status']) {
                    $errs[] = 'وضعیت بعد از اجرا دوباره تغییر کرده است.';
                } elseif ($to !== '' && $to !== $cur['status']) {
                    if ($cur['status'] === 'archived') {
                        $r = BoardService::move($c->uid, $id, 'done');
                    } elseif ($cur['status'] === 'review' && (int) ($raw['delivered_by'] ?? 0) === $c->uid) {
                        $r = BoardService::withdraw($c->uid, $id);
                    } else {
                        $r = $cur['status'] === 'done' ? BoardService::undoDone($c->uid, $id) : ['ok' => false];
                        if (!$r['ok']) {
                            $r = BoardService::setStatus($c->uid, $id, $to);
                        }
                    }
                    if (!$r['ok']) {
                        $errs[] = 'وضعیت: ' . $r['message'];
                    }
                }
            }
            $results[] = ['task' => $link, 'ok' => !$errs, 'text' => $errs ? implode(' ', array_unique($errs)) : 'به مقدار قبلی برگشت.'];
            $errs ? $fail++ : $ok++;
        }
        $c->refresh();
        DB::run("UPDATE ai_actions SET status = 'undone', undone_at = NOW() WHERE id = ?", [(int) $act['id']]);
        Audit::log($c->uid, 'ai.board.undo', ['action' => $pid, 'kind' => $act['kind'], 'project' => $c->projectId, 'ok' => $ok, 'fail' => $fail]
            + ($c->realUid ? ['real_user_id' => $c->realUid] : []));
        $blk = self::resultBlock($pid, $act['kind'], $results, $ok, $fail, false, 'undone');
        BoardAgent::note($c, 'بازگردانی: ' . fa_num($ok) . ' مورد برگشت، ' . fa_num($fail) . ' مورد برنگشت.', [$blk], ['undo' => $pid]);
        return self::res(true, '', '', 200, ['block' => $blk]);
    }

    public static function cancel(BoardContext $c, string $pid): array
    {
        $act = self::find($c, $pid);
        if ($act === null || DB::run("UPDATE ai_actions SET status = 'cancelled', token_hash = NULL WHERE id = ? AND status = 'preview'", [(int) $act['id']]) !== 1) {
            return self::res(false, 'این پیش‌نمایش دیگر فعال نیست.', 'AI_ACTION_USED', 409);
        }
        return self::res(true, 'پیش‌نمایش لغو شد؛ هیچ تغییری اعمال نشد.');
    }

    /** وضعیت فعلی عملیات برای بلوک‌های ذخیره‌شده (هنگام بارگذاری دوباره‌ی گفتگو) */
    public static function states(int $sessionId): array
    {
        $out = [];
        foreach (DB::all('SELECT id, pid, status, expires_at FROM ai_actions WHERE session_id = ?', [$sessionId]) as $a) {
            $st = $a['status'] === 'preview' && strtotime((string) $a['expires_at']) < time() ? 'expired' : (string) $a['status'];
            $out[(string) $a['pid']] = ['id' => (int) $a['id'], 'status' => $st];
        }
        return $out;
    }
}
