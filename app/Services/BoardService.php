<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\TaskModel;

/**
 * بورد پروژه (کانبان).
 * از v6.1 هر پروژه بورد خودش را دارد (ستون‌ها، نام، آیکون، رنگ و ستون شروع در BoardConfig)؛ ستون‌های rework / review / done
 * ماهیت ثابت دارند. «done» با is_completed همگام است.
 * دیدن کارت‌ها: Access::canBoardView (پیش‌فرض نقش‌ها: همه‌ی تسک‌های پروژه)؛ جستجو در بورد روی همه‌ی تسک‌های پروژه است.
 * جابه‌جایی: مجوز یک‌گزینه‌ای «تغییر وضعیت» (آزاد بین ستون‌ها) + «تکمیل تسک» برای تأیید/برگشت/بازکردن (Access::boardTargets).
 * آرشیو: تسک انجام‌شده با archived_at؛ ستون جدا بعد از «انجام‌شده». فقط نمایش برد را مرتب می‌کند و روی خود تسک
 * (انجام‌شدن، لیست‌ها، گزارش‌ها) اثری ندارد؛ پس هر کسی که کارت را در برد می‌بیند می‌تواند آرشیو/بازگردانی کند.
 */
final class BoardService
{
    /** چیدمان کارخانه (سازگاری؛ نام ستون‌های واقعی هر پروژه از BoardConfig::statuses) */
    public const STATUSES = [
        'rework'      => ['label' => 'بازبینی مسئول', 'en' => 'Rework', 'icon' => 'fa-rotate-left', 'tone' => 'orange', 'desc' => 'سرپرست ایرادی دیده و تسک برای اصلاح به مسئول برگشته'],
        'backlog'     => ['label' => 'برنامه‌ریزی‌نشده', 'en' => 'Backlog', 'icon' => 'fa-inbox', 'tone' => 'muted', 'desc' => 'ثبت شده ولی هنوز برای انجام انتخاب نشده'],
        'todo'        => ['label' => 'آماده انجام', 'en' => 'To Do', 'icon' => 'fa-list-ul', 'tone' => 'info', 'desc' => 'انتخاب شده، کار هنوز شروع نشده'],
        'in_progress' => ['label' => 'در حال انجام', 'en' => 'In Progress', 'icon' => 'fa-person-running', 'tone' => 'warn', 'desc' => 'مسئول کار را شروع کرده'],
        'review'      => ['label' => 'بررسی سرپرست', 'en' => 'In Review', 'icon' => 'fa-magnifying-glass', 'tone' => 'purple', 'desc' => 'انجام شده و منتظر بررسی سرپرست'],
        'done'        => ['label' => 'انجام‌شده', 'en' => 'Done', 'icon' => 'fa-circle-check', 'tone' => 'ok', 'desc' => 'تکمیل شده و معیارهای اتمام برآورده شده'],
    ];

    /** ستون‌های بورد پروژه‌ی تسک (کلید => اطلاعات) */
    private static function cols(array $t): array
    {
        return BoardConfig::statuses(isset($t['project_id']) ? (int) $t['project_id'] : null);
    }

    private static function lbl(array $t, string $key): string
    {
        return BoardConfig::label(isset($t['project_id']) ? (int) $t['project_id'] : null, $key);
    }

    public const MAX_LIMIT = 999;

    /** project_members.board_limits → [status => n]؛ فقط کلیدهای $keys (پیش‌فرض: هر کلید ستون معتبر) و اعداد ۰ تا MAX_LIMIT */
    public static function decodeLimits(?string $raw, ?array $keys = null): array
    {
        $a = json_decode((string) $raw, true);
        $out = [];
        if (!is_array($a)) {
            return $out;
        }
        foreach ($a as $s => $v) {
            if (is_string($s) && preg_match('/^[a-z][a-z0-9_]{1,23}$/', $s) && ($keys === null || in_array($s, $keys, true)) && is_numeric($v)) {
                $out[$s] = max(0, min(self::MAX_LIMIT, (int) $v));
            }
        }
        return $out;
    }

    /**
     * سقف مؤثر یک نفر در یک ستون: مقدار تعیین‌شده برای او (project_members.board_limits) یا پیش‌فرض نقشش
     * (عضو ۱۰، سرپرست ۲۰، مدیر واحد و مدیر کل و سازنده‌ی پروژه نامحدود). null = نامحدود.
     */
    public static function limitFor(int $projectId, int $uid, string $status): ?int
    {
        if (!in_array($status, BoardConfig::limited($projectId), true)) {
            return null;
        }
        $raw = DB::value('SELECT board_limits FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        $own = self::decodeLimits(is_string($raw) ? $raw : null);
        return array_key_exists($status, $own) ? $own[$status] : Access::roleLimit($projectId, $uid);
    }

    /**
     * سقف پر شده؟ تسک‌های مسئولِ این تسک (یا سازنده‌اش اگر مسئول ندارد) در همان ستون همان پروژه شمرده می‌شوند.
     * خروجی: پیام خطا یا null
     */
    private static function limitError(array $t, string $status): ?string
    {
        if (empty($t['project_id'])) {
            return null;
        }
        $owner = (int) ($t['assignee_id'] ?: $t['user_id']);
        $limit = self::limitFor((int) $t['project_id'], $owner, $status);
        if ($limit === null) {
            return null;
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND board_status = ? AND archived_at IS NULL AND blocked_at IS NULL AND id <> ?',
            [(int) $t['project_id'], $owner, $status, (int) $t['id']]);
        if ($n < $limit) {
            return null;
        }
        $name = full_name(DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$owner]) ?? []);
        return 'سقف ستون «' . self::lbl($t, $status) . '» برای ' . $name . ' پر است (حداکثر ' . fa_num($limit) . ' تسک).';
    }

    /**
     * ستون‌هایی که تسک‌های خود کاربر در آن‌ها از سقفش بیشتر است (مثلاً سقف بعداً از ۲۰ به ۱۰ کم شده و ۱۲ تسک دارد).
     * خروجی: status => [[project, count, limit], ...]
     */
    public static function overLimits(int $uid, int $projectId = 0): array
    {
        $sql = 'SELECT p.id, p.title FROM projects p WHERE p.blocked_at IS NULL AND (p.user_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))';
        $params = [$uid, $uid];
        if ($projectId > 0) {
            $sql .= ' AND p.id = ?';
            $params[] = $projectId;
        }
        $out = [];
        foreach (DB::all($sql, $params) as $m) {
            $pid = (int) $m['id'];
            $counts = [];
            foreach (DB::all('SELECT board_status s, COUNT(*) n FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND archived_at IS NULL AND blocked_at IS NULL GROUP BY board_status', [$pid, $uid]) as $r) {
                $counts[$r['s']] = (int) $r['n'];
            }
            foreach (BoardConfig::limited($pid) as $st) {
                if (($counts[$st] ?? 0) === 0) {
                    continue;
                }
                $limit = self::limitFor($pid, $uid, $st);
                if ($limit !== null && $counts[$st] > $limit) {
                    $out[$st][] = ['project' => (string) $m['title'], 'count' => $counts[$st], 'limit' => $limit];
                }
            }
        }
        return $out;
    }


    /** حداکثر کارت‌های ستون «انجام‌شده» (جدیدترین‌ها) تا برد شلوغ نشود */
    private const DONE_LIMIT = 30;

    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** پروژه‌هایی که کاربر در آن‌هاست (سازنده یا عضو). */
    public static function projects(int $uid): array
    {
        return DB::all('SELECT p.id, p.title, p.profile_image FROM projects p WHERE (p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)) AND p.blocked_at IS NULL ORDER BY p.title', [$uid, $uid]);
    }

    /** WHERE مشترک برد و آرشیو با فیلترها (اولین پارامتر = بیننده برای دسترسی تسک). */
    private static function where(int $uid, array $f): array
    {
        $where = '(t.user_id = ? OR t.assignee_id = ? OR t.project_id IN (SELECT id FROM projects WHERE user_id = ?) OR t.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))';
        $params = [$uid, $uid, $uid, $uid, $uid];
        if (!empty($f['project'])) {
            $where .= ' AND t.project_id = ?';
            $params[] = (int) $f['project'];
        }
        if (!empty($f['mine'])) {
            $where .= ' AND ' . TaskModel::mine($uid);
            $params[] = $uid;
        }
        if (\App\Core\Search::tokens((string) ($f['q'] ?? ''))) {
            [$qs, $qp] = \App\Core\Search::sql(['t.title', 't.description'], (string) $f['q']);
            $wp = array_slice($params, 1);                 // پارامتر اول مال select بیرونی (بیننده) است
            if ((int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $where . ' AND ' . $qs, array_merge($wp, $qp)) === 0
                && ($fz = \App\Core\Search::fuzzyIds(DB::all('SELECT t.id, t.title FROM tasks t WHERE ' . $where . ' ORDER BY t.id DESC LIMIT ' . \App\Core\Search::FUZZY_SCAN, $wp), (string) $f['q'], ['title']))) {
                $where .= ' AND t.id IN (' . DB::in($fz) . ')';
                $params = array_merge($params, $fz);
            } else {
                $where .= ' AND ' . $qs;
                $params = array_merge($params, $qp);
            }
        }
        return [$where, $params];
    }

    /** آماده‌سازی کارت برای نما: مقصدهای مجاز، لینک و برجستگی */
    private static function card(array $t, int $uid, string $st, int $focus): array
    {
        $t['moves'] = self::targets($t, $uid, $st);
        $t['can_move'] = $t['moves'] !== [];
        $t['lock_reason'] = $t['can_move'] ? null : self::lockReason($t, $uid, $st);
        $t['is_focus'] = (int) $t['id'] === $focus;
        $t['token'] = $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']);
        return $t;
    }

    /** مقصدهای مجاز کارت: مراحل طبق دسترسی برد + آرشیو (از انجام‌شده) / بازگشت به انجام‌شده (از آرشیو) */
    private static function targets(array $t, int $uid, string $st): array
    {
        if ($st === 'archived') {
            return self::canArchive($t, $uid) ? ['done'] : [];
        }
        $out = Access::boardTargets($t, $uid, $st, array_keys(self::cols($t)));
        if ($st === 'done' && self::canArchive($t, $uid)) {
            $out[] = 'archived';
        }
        return $out;
    }

    /**
     * کارت در برد دیده شود؟ بدون جستجو: Access::canBoardView؛ با جستجو (v6.1): همه‌ی تسک‌های پروژه برای هر عضو آن،
     * به‌جز تسک محرمانه‌ای که کاربر جزو بیننده‌هایش نیست.
     */
    private static function visible(array $t, int $uid, array $f): bool
    {
        if (\App\Core\Search::tokens((string) ($f['q'] ?? '')) && !empty($t['project_id'])) {
            static $member = [];
            $pid = (int) $t['project_id'];
            return Access::canSeeConfidential($t, $uid) && ($member[$pid . ':' . $uid] ??= Access::isProjectMember($pid, $uid));
        }
        return Access::canBoardView($t, $uid);
    }

    /** آرشیو کردن / برگرداندن از آرشیو: فقط مخصوص برد است، پس هر کسی که کارت را در برد می‌بیند */
    public static function canArchive(array $t, int $uid): bool
    {
        return Access::canBoardView($t, $uid);
    }

    /**
     * کارت‌های برد، گروه‌بندی‌شده بر اساس وضعیت (بدون آرشیوشده‌ها).
     * فقط تسک‌هایی که کاربر اجازه‌ی دیدنشان را دارد (Access::canBoardView).
     * @param array $f project (شناسه یا ۰)، mine (فقط مسئولیت من)، q (جستجو در عنوان)، focus (تسکی که برجسته می‌شود؛ همیشه نمایش داده می‌شود)
     * @return array{0: array, 1: int} [ستون‌ها، تعداد تسک‌های انجام‌شده‌ای که کاربر می‌تواند آرشیو کند]
     */
    public static function columns(int $uid, array $f): array
    {
        [$where, $params] = self::where($uid, $f);
        $rows = TaskModel::board($where . ' AND t.archived_at IS NULL', $params);
        // کارت برجسته همیشه هست، حتی اگر بیرون از سقف تعداد کارت‌ها افتاده باشد
        $focus = (int) ($f['focus'] ?? 0);
        if ($focus > 0 && !in_array($focus, array_map(static fn($r) => (int) $r['id'], $rows), true)) {
            $rows = array_merge($rows, TaskModel::board('t.id = ? AND t.archived_at IS NULL', [$uid, $focus]));
        }

        $cols = array_fill_keys(BoardConfig::keys((int) ($f['project'] ?? 0)), []);
        $archivable = 0;
        foreach ($rows as $t) {
            if (!self::visible($t, $uid, $f)) {
                continue;
            }
            $st = self::status($t);
            if ($st === 'done' && self::canArchive($t, $uid)) {
                $archivable++;
            }
            if ($st === 'done' && count($cols['done']) >= self::DONE_LIMIT && (int) $t['id'] !== $focus) {
                continue;
            }
            $cols[$st][] = self::card($t, $uid, $st, $focus);
        }
        return [$cols, $archivable];
    }

    /**
     * آرشیو: جدیدترین‌ها اول.
     * @return array{total:int, cards:array} cards = $limit مورد اول (۰ = همه)
     */
    public static function archive(int $uid, array $f, int $limit = 5): array
    {
        [$where, $params] = self::where($uid, $f);
        $total = 0;
        $cards = [];
        $focus = (int) ($f['focus'] ?? 0);
        foreach (TaskModel::archived($where, $params) as $t) {
            if (!self::visible($t, $uid, $f)) {
                continue;
            }
            $total++;
            if ($limit === 0 || count($cards) < $limit || (int) $t['id'] === $focus) {
                $cards[] = self::card($t, $uid, 'archived', $focus);
            }
        }
        return ['total' => $total, 'cards' => $cards];
    }

    /** همه‌ی تسک‌های انجام‌شده‌ی برد (با همان فیلترها) که کاربر اجازه‌اش را دارد → آرشیو */
    public static function archiveDone(int $uid, array $f): array
    {
        [$where, $params] = self::where($uid, $f);
        $ids = [];
        foreach (TaskModel::board($where . " AND t.archived_at IS NULL AND (t.board_status = 'done' OR t.is_completed = 1)", $params, 5000) as $t) {
            if (self::status($t) === 'done' && Access::canBoardView($t, $uid) && self::canArchive($t, $uid)) {
                $ids[] = (int) $t['id'];
            }
        }
        if (!$ids) {
            return self::res(false, 'تسک انجام‌شده‌ای برای آرشیو نیست.', 'NOTHING_TO_ARCHIVE', 422);
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::run('UPDATE tasks SET archived_at = NOW() WHERE archived_at IS NULL AND id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
        }
        foreach ($ids as $tid) {
            TaskHistory::log($tid, $uid, 'archived');
        }
        return self::res(true, fa_num(count($ids)) . ' تسک انجام‌شده به آرشیو منتقل شد.', '', 200, ['archived' => count($ids)]);
    }

    /** وضعیت فعلی تسک در برد (archived برای آرشیوشده) */
    public static function statusOf(array $t): string
    {
        return self::status($t);
    }

    private static function status(array $t): string
    {
        if (!empty($t['archived_at'])) {
            return 'archived';
        }
        // کلیدی که در بورد پروژه نیست (ستون حذف‌شده، تسک قدیمی) = ستون شروع همان پروژه
        $pid = isset($t['project_id']) ? (int) $t['project_id'] : null;
        if ((int) ($t['is_completed'] ?? 0) === 1) {
            return 'done';
        }
        return BoardConfig::has($pid, (string) ($t['board_status'] ?? '')) ? (string) $t['board_status'] : BoardConfig::start($pid);
    }

    /** وضعیت بورد تسک برای جزئیات: وضعیت فعلی، مرحله‌ی بعد، اجازه‌ی انتقال یا دلیل قفل بودن */
    public static function taskState(array $t, int $uid): array
    {
        $st = self::status($t);
        if (!empty($t['blocked_at'])) {
            $info = BoardConfig::info(isset($t['project_id']) ? (int) $t['project_id'] : null, $st);
            $why = 'این تسک مسدود شده است؛ تا مسدودی‌اش برداشته نشود وضعیتش عوض نمی‌شود.';
            return ['targets' => [], 'lock' => $why, 'status' => $st, 'label' => $info['label'], 'icon' => $info['icon'], 'tone' => $info['tone'],
                'next' => null, 'next_label' => null, 'can_next' => false, 'lock_reason' => null];
        }
        $pid = isset($t['project_id']) ? (int) $t['project_id'] : null;
        $cols = self::cols($t);
        $info = BoardConfig::info($pid, $st);
        $next = BoardConfig::next($pid, $st);
        $can = $next !== null && in_array($next, Access::boardTargets($t, $uid, $st, array_keys($cols)), true)
            && ($next !== 'done' || Access::canTask($t, $uid, 'complete'));
        // مقصدهای مجاز برای پنجره‌ی تغییر وضعیت (انجام‌شده فقط با مجوز تکمیل)
        $targets = [];
        if ($st !== 'archived') {
            foreach (Access::boardTargets($t, $uid, $st, array_keys($cols)) as $k) {
                if ($k !== 'done' || Access::canTask($t, $uid, 'complete')) {
                    $targets[] = ['key' => $k, 'label' => $cols[$k]['label'], 'icon' => $cols[$k]['icon'], 'tone' => $cols[$k]['tone']];
                }
            }
        }
        return [
            'targets' => $targets, 'lock' => $targets ? null : self::lockReason($t, $uid, $st),
            'status' => $st, 'label' => $info['label'], 'icon' => $info['icon'], 'tone' => $info['tone'],
            'next' => $next, 'next_label' => $next !== null ? BoardConfig::label($pid, $next) : null,
            'can_next' => $can, 'lock_reason' => $next !== null && !$can ? self::lockReason($t, $uid, $st, $next) : null,
        ];
    }

    /**
     * چرا کاربر نمی‌تواند وضعیت این تسک را عوض کند (قفل روی کارت بورد / دکمه‌ی «انتقال به …»).
     * $to: مقصد مشخص (اختیاری)؛ بدون آن، دلیل کلی قفل بودن کارت.
     */
    public static function lockReason(array $t, int $uid, string $st, ?string $to = null): string
    {
        static $owners = [];
        $pid = (int) ($t['project_id'] ?? 0);
        if ($pid <= 0) {
            return 'این تسک به پروژه‌ای وصل نیست؛ جابه‌جایی در بورد فقط برای تسک‌های پروژه و طبق دسترسی اعضا ممکن است. تسک را در یک پروژه قرار دهید.';
        }
        if (!isset($owners[$pid])) {
            $p = DB::one('SELECT p.title, u.first_name, u.last_name FROM projects p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$pid]);
            $owners[$pid] = [$p['title'] ?? '', $p ? full_name($p) : ''];
        }
        [$ptitle, $owner] = $owners[$pid];
        $ask = $owner !== '' ? ' برای گرفتن دسترسی با مدیر پروژه (' . $owner . ') هماهنگ کنید.' : '';
        $from = self::lbl($t, $st);
        if ($to === 'done' && !Access::canTask($t, $uid, 'complete')) {
            return 'دسترسی «تکمیل تسک» در پروژه‌ی «' . $ptitle . '» به شما داده نشده است.' . $ask;
        }
        $complete = Access::canTask($t, $uid, 'complete');
        if ($st === 'done' && !$complete) {
            return 'بازکردن تسک انجام‌شده دسترسی «تکمیل تسک» می‌خواهد که در پروژه‌ی «' . $ptitle . '» به شما داده نشده است.' . $ask;
        }
        if ($to === 'rework' && !$complete) {
            return 'برگرداندن تسک برای اصلاح کار سرپرست است (دسترسی «تکمیل تسک» در پروژه‌ی «' . $ptitle . '»).' . $ask;
        }
        if ($to === 'done' && $st !== 'review') {
            return 'تسک اول باید با تیک «انجام شد» به «' . self::lbl($t, 'review') . '» برود؛ تکمیل فقط با تأیید در همان مرحله است.';
        }
        if (!in_array('change_status', Access::boardPerms($t, $uid), true)) {
            return 'دسترسی «' . Access::BOARD_PERMS['change_status']['label'] . '» در پروژه‌ی «' . $ptitle . '» به شما داده نشده است؛ فقط می‌توانید کارت را ببینید.' . $ask;
        }
        return 'جابه‌جایی کارت از «' . $from . '»' . ($to !== null ? ' به «' . self::lbl($t, $to) . '»' : '') . ' برای شما ممکن نیست.' . $ask;
    }

    /** تغییر وضعیت بورد از جزئیات تسک (پنجره‌ی کوچک روی نشان وضعیت)؛ همان قواعد بورد، بدون نیاز به دیدن کارت در بورد */
    public static function setStatus(int $actor, int $id, string $status, string $reason = ''): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view') || !Access::canBoardView($t, $actor)) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if ($status === 'archived' || !empty($t['archived_at'])) {
            return self::res(false, 'وضعیت نامعتبر است.', 'STATUS_INVALID', 422);
        }
        return self::move($actor, $id, $status, $reason, true);
    }

    /** انتقال تسک به مرحله‌ی بعد از جزئیات تسک ($from = وضعیتی که کاربر دیده بود، تا دو کلیک پشت‌سرهم دو مرحله جلو نبرد) */
    public static function advance(int $actor, int $id, string $from): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view') || !Access::canBoardView($t, $actor)) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $st = self::status($t);
        if ($from !== '' && $from !== $st) {
            return self::res(false, 'وضعیت این تسک در این فاصله تغییر کرده است؛ جزئیات را دوباره باز کنید.', 'STATUS_CHANGED', 409);
        }
        $next = BoardConfig::next(isset($t['project_id']) ? (int) $t['project_id'] : null, $st);
        if ($next === null) {
            return self::res(false, 'این تسک مرحله‌ی بعدی در بورد ندارد.', 'NO_NEXT', 422);
        }
        return self::move($actor, $id, $next, '', true);
    }

    /** آیا تسک در برد کاربر دیده می‌شود؟ (برای آیکون «نمایش در برد» در جزئیات تسک) */
    public static function canSee(array $t, int $uid): bool
    {
        return Access::canBoardView($t, $uid);
    }

    /**
     * کسانی که می‌توانند تسکِ «بررسی سرپرست» را تکمیل کنند (اعلان رسیدن تسک به بررسی):
     * سازنده‌ی پروژه و اعضایی که هم مجوز انتقال به انجام‌شده در بورد و هم مجوز «تکمیل تسک» دارند (بجز خود انجام‌دهنده).
     */
    public static function reviewers(array $t, int $except = 0): array
    {
        $pid = (int) ($t['project_id'] ?? 0);
        if ($pid <= 0) {
            return [];
        }
        $ids = array_map('intval', DB::column('SELECT user_id FROM projects WHERE id = ? UNION SELECT user_id FROM project_members WHERE project_id = ?', [$pid, $pid]));
        $out = [];
        foreach (array_unique($ids) as $uid) {
            if ($uid !== $except && $uid > 0 && in_array('done', Access::boardTargets($t, $uid, 'review', ['done']), true) && Access::canTask($t, $uid, 'complete')) {
                $out[] = $uid;
            }
        }
        return $out;
    }

    /**
     * «منتظر اقدام» پیشخوان:
     * review = تسک‌های «بررسی سرپرست» در پروژه‌های کاربر که او می‌تواند تأیید/تکمیل کند (سرپرست، مدیر، سازنده‌ی پروژه)؛
     * rework = تسک‌هایی که مسئولشان کاربر است و سرپرست برای اصلاح به «بازبینی مسئول» برگردانده.
     */
    public static function waiting(int $uid, int $limit = 10): array
    {
        // پیش‌نمایش برای سرپرست پیش از تأیید (v5.4): آخرین یادداشت مسئول، تعداد پیوست‌ها، سررسید (تحویل نسبت به سررسید)
        $sel = 'SELECT t.id, t.title, t.share_token, t.user_id, t.assignee_id, t.project_id, t.blocked_reason, t.board_moved_at, t.delivered_at, t.due_date, t.due_time, t.is_confidential, p.title AS project_title,
                u.first_name, u.last_name, u.avatar,
                (SELECT COUNT(*) FROM task_attachments a WHERE a.task_id = t.id) AS files_count,
                (SELECT n.note FROM task_notes n WHERE n.task_id = t.id AND (n.user_id = t.assignee_id OR (t.assignee_id IS NULL AND n.user_id = t.user_id)) ORDER BY n.id DESC LIMIT 1) AS last_note,
                dv.first_name AS deliverer_first, dv.last_name AS deliverer_last
                FROM tasks t LEFT JOIN projects p ON p.id = t.project_id LEFT JOIN users u ON u.id = COALESCE(t.assignee_id, t.user_id)
                LEFT JOIN users dv ON dv.id = t.delivered_by';
        $review = [];
        foreach (DB::all($sel . " WHERE t.board_status = 'review' AND t.archived_at IS NULL AND t.blocked_at IS NULL AND t.is_completed = 0
                AND (p.user_id = ? OR t.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)) ORDER BY t.board_moved_at DESC, t.id DESC LIMIT 200", [$uid, $uid]) as $t) {
            if (in_array('done', Access::boardTargets($t, $uid, 'review', ['done']), true) && Access::canTask($t, $uid, 'complete')) {
                $review[] = $t;
                if (count($review) >= $limit) {
                    break;
                }
            }
        }
        // برگشت‌خورده: مسئولش منم، یا بی‌مسئول و سازنده‌اش منم
        $rework = DB::all($sel . " WHERE t.board_status = 'rework' AND t.archived_at IS NULL AND t.blocked_at IS NULL
                AND (t.assignee_id = ? OR (t.assignee_id IS NULL AND t.user_id = ?))
                ORDER BY t.board_moved_at DESC, t.id DESC LIMIT " . max(1, $limit), [$uid, $uid]);
        return ['review' => $review, 'rework' => $rework];
    }

    /** انتقال کارت به ستون دیگر. */
    /** $fromTask: از تیک «انجام شد» در لیست تسک‌ها (دیدن تسک قبلاً با مجوز view بررسی شده؛ دیدن کارت در برد لازم نیست) */
    public static function move(int $actor, int $id, string $status, string $reason = '', bool $fromTask = false): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || (!$fromTask && !Access::canBoardView($t, $actor)) || !Access::canSeeConfidential($t, $actor)) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!isset(self::cols($t)[$status]) && $status !== 'archived') {
            return self::res(false, 'وضعیت نامعتبر است.', 'STATUS_INVALID', 422);
        }
        if (!empty($t['blocked_at'])) {
            return self::res(false, 'این تسک مسدود شده است؛ ابتدا مسدودی آن را بردارید.', 'TASK_BLOCKED', 409);
        }
        $from = self::status($t);
        // آرشیو: فقط از «انجام‌شده» به آرشیو و از آرشیو به «انجام‌شده»
        if ($status === 'archived' || $from === 'archived') {
            if ($from === $status) {
                return self::res(true, 'تسک همین حالا در آرشیو است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => true]);
            }
            if (!in_array($status, self::targets($t, $actor, $from), true)) {
                return self::res(false, $from === 'archived' ? 'تسک آرشیوشده فقط به «انجام‌شده» برمی‌گردد.' : 'فقط تسک‌های انجام‌شده آرشیو می‌شوند.', 'FORBIDDEN', 403);
            }
            DB::run('UPDATE tasks SET archived_at = ' . ($status === 'archived' ? 'NOW()' : 'NULL') . ', board_moved_at = NOW() WHERE id = ?', [$id]);
            TaskHistory::log($id, $actor, $status === 'archived' ? 'archived' : 'unarchived');
            return self::res(true, $status === 'archived' ? 'تسک به آرشیو منتقل شد.' : 'تسک از آرشیو به «انجام‌شده» برگشت.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => true]);
        }
        if ($from === $status) {
            return self::res(true, 'تسک همین حالا در «' . self::lbl($t, $status) . '» است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $status === 'done']);
        }
        if ($status === 'done' && !Access::canTask($t, $actor, 'complete')) {
            return self::res(false, 'شما دسترسی لازم برای تکمیل تسک را ندارید.', 'FORBIDDEN', 403);
        }
        // تکمیل فقط با تأیید در «بررسی سرپرست» (v4.1): هیچ‌کس، حتی مدیر کل، مستقیم به «انجام‌شده» نمی‌برد
        if ($status === 'done' && $from !== 'review') {
            return self::res(false, 'تسک اول باید با تیک «انجام شد» به «بررسی سرپرست» برود؛ تکمیل فقط با تأیید در همان مرحله است.', 'REVIEW_REQUIRED', 409);
        }
        if (!in_array($status, Access::boardTargets($t, $actor, $from, array_keys(self::cols($t))), true)) {
            return self::res(false, 'اجازه‌ی انتقال این تسک از «' . self::lbl($t, $from) . '» به «' . self::lbl($t, $status) . '» را ندارید.', 'FORBIDDEN', 403);
        }
        if ($err = self::limitError($t, $status)) {
            return self::res(false, $err, 'BOARD_LIMIT', 409);
        }
        self::apply($t, $actor, $from, $status, $reason);
        return self::res(true, 'تسک به «' . self::lbl($t, $status) . '» منتقل شد.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $status === 'done']);
    }

    /**
     * ثبت تغییر وضعیت (بعد از همه‌ی بررسی‌ها) + ستون‌های تحویل/تأیید برای KPI:
     * review ← delivered_at/by = حالا/انجام‌دهنده؛ done ← approved_by = تأییدکننده (delivered_at می‌ماند)؛ مراحل قبل ← تحویل پاک می‌شود.
     */
    private static function apply(array $t, int $actor, string $from, string $status, string $reason = ''): void
    {
        $id = (int) $t['id'];
        // علت: برای «بازبینی مسئول» ایرادی که سرپرست دیده (ستون blocked_reason؛ نامش از ستون «مسدودشده»ی قدیمی بورد مانده)
        $reason = trim(mb_substr($reason, 0, 255));
        $reason = $status === 'rework' && $reason !== '' ? $reason : null;
        $done = $status === 'done';
        $review = $status === 'review';
        // is_quick = 0: اولین تغییر وضعیت در بورد علامت «ثبت سریع» را برمی‌دارد
        DB::run('UPDATE tasks SET board_status = ?, blocked_reason = ?, board_moved_at = NOW(), archived_at = NULL, is_completed = ?, is_quick = 0,
                   completed_at = CASE WHEN ? = 1 THEN COALESCE(completed_at, NOW()) ELSE NULL END,
                   delivered_at = CASE WHEN ? = 1 THEN NOW() WHEN ? = 1 THEN COALESCE(delivered_at, NOW()) ELSE NULL END,
                   delivered_by = CASE WHEN ? = 1 THEN ? WHEN ? = 1 THEN COALESCE(delivered_by, ?) ELSE NULL END,
                   approved_by = CASE WHEN ? = 1 THEN ? ELSE NULL END WHERE id = ?',
            [$status, $reason, $done ? 1 : 0, $done ? 1 : 0, $review ? 1 : 0, $done ? 1 : 0, $review ? 1 : 0, $actor, $done ? 1 : 0, $actor, $done ? 1 : 0, $actor, $id]);
        TaskHistory::log($id, $actor, 'board', ['from' => $from, 'to' => $status, 'reason' => $reason]);
        if ($review && $from !== 'done') {
            Notifier::taskInReview($t, $actor, self::reviewers($t, $actor));
        } elseif ($status === 'rework') {
            // یادداشت خودکار روی تسک با علت برگشت؛ نویسنده = کسی که تسک را برگردانده
            DB::insert('task_notes', ['task_id' => $id, 'user_id' => $actor,
                'note' => '↩️ برگشت به «' . self::lbl($t, 'rework') . '» برای اصلاح' . "\n" . 'علت: ' . ($reason ?? 'ذکر نشده')]);
            Notifier::taskReturned($t, $actor, $reason);
        }
    }

    /**
     * تیک «انجام شد» (v4.1): برای همه — حتی مدیر کل و سرپرست — تسک را به «بررسی سرپرست» می‌برد (تحویل).
     * مجوز مرحله‌های برد لازم نیست؛ کافی است کاربر مسئول، سازنده یا دارای مجوز تکمیل/ارسال برای بررسی باشد (Access::canDeliver).
     * تکمیل نهایی فقط با تأیید در «بررسی سرپرست» است (move به done).
     */
    public static function deliver(int $actor, int $id): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!empty($t['blocked_at'])) {
            return self::res(false, 'این تسک مسدود شده است؛ ابتدا مسدودی آن را بردارید.', 'TASK_BLOCKED', 409);
        }
        if (!Access::canDeliver($t, $actor)) {
            return self::res(false, 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را برای بررسی بفرستد.', 'FORBIDDEN', 403);
        }
        $from = self::status($t);
        if ($from === 'review') {
            return self::res(true, 'این تسک همین حالا منتظر تأیید سرپرست است.', '', 200, ['id' => $id, 'status' => 'review', 'is_completed' => false]);
        }
        if ($from === 'done' || $from === 'archived') {
            return self::res(false, 'این تسک انجام‌شده است.', 'ALREADY_COMPLETED', 409);
        }
        // دارنده‌ی دسترسی تأیید (سرپرست، سازنده‌ی پروژه، …): تیک مستقیم «انجام‌شده» (v5.4)؛ زمان تحویل و تأیید هر دو همین لحظه
        if (Access::canApprove($t, $actor)) {
            self::apply($t, $actor, $from, 'done');
            return self::res(true, 'تسک انجام شد.', '', 200, ['id' => $id, 'status' => 'done', 'from' => $from, 'is_completed' => true]);
        }
        if ($err = self::limitError($t, 'review')) {
            return self::res(false, $err, 'BOARD_LIMIT', 409);
        }
        self::apply($t, $actor, $from, 'review');
        return self::res(true, 'تسک انجام شد و برای «' . self::lbl($t, 'review') . '» ارسال شد.', '', 200, ['id' => $id, 'status' => 'review', 'from' => $from, 'is_completed' => false]);
    }

    /** چند دقیقه بعد از «انجام شد»/«تأیید» هنوز دکمه‌ی «بازگردانی» کار می‌کند */
    public const UNDO_DONE_MINUTES = 10;

    /**
     * «بازگردانی» بعد از تکمیل مستقیم با تیک یا تأیید در «بررسی سرپرست» (v5.4): فقط همان کسی که تأیید کرده، تا UNDO_DONE_MINUTES دقیقه؛
     * تسک به وضعیت قبل از تکمیل (مثلاً «بررسی سرپرست» یا «در حال انجام») برمی‌گردد.
     */
    public static function undoDone(int $actor, int $id): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (self::status($t) !== 'done' || (int) ($t['approved_by'] ?? 0) !== $actor || !empty($t['blocked_at'])
            || strtotime((string) $t['completed_at']) < time() - self::UNDO_DONE_MINUTES * 60) {
            return self::res(false, 'این تسک دیگر قابل بازگرداندن نیست.', 'CANNOT_UNDO', 409);
        }
        $last = DB::value("SELECT data FROM task_events WHERE task_id = ? AND type = 'board' ORDER BY id DESC LIMIT 1", [$id]);
        $prev = json_decode((string) $last, true)['from'] ?? 'review';
        if (!isset(self::cols($t)[$prev]) || in_array($prev, ['done', 'archived'], true)) {
            $prev = 'review';
        }
        self::apply($t, $actor, 'done', $prev);
        return self::res(true, 'تکمیل تسک برگشت؛ تسک به «' . self::lbl($t, $prev) . '» برگشت.', '', 200, ['id' => $id, 'status' => $prev, 'is_completed' => false]);
    }

    /** پس گرفتن تحویل (دکمه‌ی «بازگردانی» بعد از تیک): فقط همان کسی که تحویل داده، تا وقتی هنوز تأیید نشده؛ برمی‌گردد به وضعیت قبل از تحویل */
    public static function withdraw(int $actor, int $id): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (self::status($t) !== 'review' || (int) ($t['delivered_by'] ?? 0) !== $actor) {
            return self::res(false, 'این تسک دیگر قابل بازگرداندن نیست (تأیید یا جابه‌جا شده است).', 'CANNOT_WITHDRAW', 409);
        }
        $last = DB::value("SELECT data FROM task_events WHERE task_id = ? AND type = 'board' ORDER BY id DESC LIMIT 1", [$id]);
        $prev = json_decode((string) $last, true)['from'] ?? '';
        if (!isset(self::cols($t)[$prev]) || in_array($prev, ['review', 'done'], true)) {
            $prev = BoardConfig::start(isset($t['project_id']) ? (int) $t['project_id'] : null);
        }
        self::apply($t, $actor, 'review', $prev);
        return self::res(true, 'تحویل پس گرفته شد؛ تسک به «' . self::lbl($t, $prev) . '» برگشت.', '', 200, ['id' => $id, 'status' => $prev, 'is_completed' => false]);
    }
}
