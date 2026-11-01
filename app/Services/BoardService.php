<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\TaskModel;

/**
 * بورد پروژه (کانبان).
 * هر تسک یک وضعیت کانبان دارد؛ «done» با is_completed همگام است.
 * دیدن کارت‌ها: تسک‌های خود کاربر + تسک‌های اعضایی که در «مشاهده‌ی تسک‌های» برایش انتخاب شده (Access::canBoardView).
 * جابه‌جایی با «دسترسی بورد پروژه» هر پروژه است (Access::BOARD_PERMS، جدا از دسترسی تسک؛ پیش‌فرض بسته).
 * هر جابه‌جایی فقط یک مرحله جلو/عقب است، مگر با «جابه‌جایی آزاد».
 * آرشیو: تسک انجام‌شده با archived_at؛ ستون جدا بعد از «انجام‌شده». فقط نمایش برد را مرتب می‌کند و روی خود تسک
 * (انجام‌شدن، لیست‌ها، گزارش‌ها) اثری ندارد؛ پس هر کسی که کارت را در برد می‌بیند می‌تواند آرشیو/بازگردانی کند.
 */
final class BoardService
{
    public const STATUSES = [
        'rework'      => ['label' => 'بازبینی مسئول', 'en' => 'Rework', 'icon' => 'fa-rotate-left', 'tone' => 'orange', 'desc' => 'سرپرست ایرادی دیده و تسک برای اصلاح به مسئول برگشته'],
        'backlog'     => ['label' => 'برنامه‌ریزی‌نشده', 'en' => 'Backlog', 'icon' => 'fa-inbox', 'tone' => 'muted', 'desc' => 'ثبت شده ولی هنوز برای انجام انتخاب نشده'],
        'todo'        => ['label' => 'آماده انجام', 'en' => 'To Do', 'icon' => 'fa-list-ul', 'tone' => 'info', 'desc' => 'انتخاب شده، کار هنوز شروع نشده'],
        'in_progress' => ['label' => 'در حال انجام', 'en' => 'In Progress', 'icon' => 'fa-person-running', 'tone' => 'warn', 'desc' => 'مسئول کار را شروع کرده'],
        'review'      => ['label' => 'بررسی سرپرست', 'en' => 'In Review', 'icon' => 'fa-magnifying-glass', 'tone' => 'purple', 'desc' => 'انجام شده و منتظر بررسی سرپرست'],
        'done'        => ['label' => 'انجام‌شده', 'en' => 'Done', 'icon' => 'fa-circle-check', 'tone' => 'ok', 'desc' => 'تکمیل شده و معیارهای اتمام برآورده شده'],
        'blocked'     => ['label' => 'مسدودشده', 'en' => 'Blocked', 'icon' => 'fa-ban', 'tone' => 'danger', 'desc' => 'متوقف به‌خاطر وابستگی، مشکل فنی یا انتظار'],
    ];

    /**
     * ترتیب پیش‌فرض ستون‌ها (همان ترتیب STATUSES + آرشیو). هر کاربر می‌تواند ترتیب خودش را بچیند (users.board_order).
     */
    public const DEFAULT_ORDER = ['rework', 'backlog', 'todo', 'in_progress', 'review', 'done', 'blocked', 'archived'];

    /** ترتیب ستون‌های کاربر: کلیدهای نامعتبر حذف و ستون‌های جاافتاده (مثلاً وضعیت تازه) در جای پیش‌فرضشان اضافه می‌شوند */
    public static function order(?string $raw): array
    {
        $want = array_values(array_unique(array_intersect(array_map('trim', explode(',', (string) $raw)), self::DEFAULT_ORDER)));
        if (!$want) {
            return self::DEFAULT_ORDER;
        }
        foreach (self::DEFAULT_ORDER as $i => $k) {
            if (!in_array($k, $want, true)) {
                // بعد از ستونی که در پیش‌فرض قبل از آن است
                $prev = $i > 0 ? array_search(self::DEFAULT_ORDER[$i - 1], $want, true) : -1;
                array_splice($want, $prev === false ? count($want) : $prev + 1, 0, [$k]);
            }
        }
        return $want;
    }

    /** ذخیره‌ی ترتیب ستون‌ها برای کاربر؛ ترتیب خالی یا برابر پیش‌فرض = NULL (بازگشت به پیش‌فرض) */
    public static function saveOrder(int $uid, array $keys): array
    {
        $keys = array_values(array_map('strval', $keys));
        if ($keys && (count($keys) !== count(self::DEFAULT_ORDER) || array_diff(self::DEFAULT_ORDER, $keys))) {
            return self::res(false, 'ترتیب ستون‌ها نامعتبر است.', 'ORDER_INVALID', 422);
        }
        $value = $keys && $keys !== self::DEFAULT_ORDER ? implode(',', $keys) : null;
        DB::run('UPDATE users SET board_order = ? WHERE id = ?', [$value, $uid]);
        return self::res(true, $value === null ? 'ترتیب ستون‌ها به حالت پیش‌فرض برگشت.' : 'ترتیب ستون‌ها ذخیره شد.', '', 200, ['order' => self::order($value), 'is_default' => $value === null]);
    }

    /** وضعیت‌هایی که برای هر عضو سقف تعداد تسک دارند (برنامه‌ریزی‌نشده، انجام‌شده و آرشیو سقف ندارند) */
    public const LIMITED = ['todo', 'in_progress', 'blocked', 'review', 'rework'];
    public const MAX_LIMIT = 999;

    /** project_members.board_limits → [status => n]؛ فقط وضعیت‌های LIMITED و اعداد ۰ تا MAX_LIMIT */
    public static function decodeLimits(?string $raw): array
    {
        $a = json_decode((string) $raw, true);
        $out = [];
        foreach (self::LIMITED as $s) {
            if (is_array($a) && isset($a[$s]) && is_numeric($a[$s])) {
                $out[$s] = max(0, min(self::MAX_LIMIT, (int) $a[$s]));
            }
        }
        return $out;
    }

    /**
     * سقف پر شده؟ تسک‌های مسئولِ این تسک (یا سازنده‌اش اگر مسئول ندارد) در همان ستون همان پروژه شمرده می‌شوند.
     * خروجی: پیام خطا یا null
     */
    private static function limitError(array $t, string $status): ?string
    {
        if (!in_array($status, self::LIMITED, true) || empty($t['project_id'])) {
            return null;
        }
        $owner = (int) ($t['assignee_id'] ?: $t['user_id']);
        $limit = self::decodeLimits(DB::value('SELECT board_limits FROM project_members WHERE project_id = ? AND user_id = ?', [(int) $t['project_id'], $owner]))[$status] ?? null;
        if ($limit === null) {
            return null;
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND board_status = ? AND archived_at IS NULL AND id <> ?',
            [(int) $t['project_id'], $owner, $status, (int) $t['id']]);
        if ($n < $limit) {
            return null;
        }
        $name = full_name(DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$owner]) ?? []);
        return 'سقف ستون «' . self::STATUSES[$status]['label'] . '» برای ' . $name . ' پر است (حداکثر ' . fa_num($limit) . ' تسک).';
    }

    /**
     * ستون‌هایی که تسک‌های خود کاربر در آن‌ها از سقفش بیشتر است (مثلاً سقف بعداً از ۱۰ به ۵ کم شده و ۷ تسک دارد).
     * فقط پروژه‌ی فیلترشده یا همه‌ی پروژه‌های کاربر. خروجی: status => [[project, count, limit], ...]
     */
    public static function overLimits(int $uid, int $projectId = 0): array
    {
        $sql = 'SELECT pm.project_id, pm.board_limits, p.title FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = ? AND pm.board_limits IS NOT NULL';
        $params = [$uid];
        if ($projectId > 0) {
            $sql .= ' AND pm.project_id = ?';
            $params[] = $projectId;
        }
        $out = [];
        foreach (DB::all($sql, $params) as $m) {
            $limits = self::decodeLimits($m['board_limits']);
            if (!$limits) {
                continue;
            }
            $counts = [];
            foreach (DB::all('SELECT board_status s, COUNT(*) n FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND archived_at IS NULL GROUP BY board_status', [(int) $m['project_id'], $uid]) as $r) {
                $counts[$r['s']] = (int) $r['n'];
            }
            foreach ($limits as $st => $limit) {
                if (($counts[$st] ?? 0) > $limit) {
                    $out[$st][] = ['project' => (string) $m['title'], 'count' => $counts[$st], 'limit' => $limit];
                }
            }
        }
        return $out;
    }

    /** مرحله‌ی بعدی هر وضعیت (دکمه‌ی «انتقال به …» در جزئیات تسک) */
    public const NEXT = ['backlog' => 'todo', 'todo' => 'in_progress', 'in_progress' => 'review', 'review' => 'done', 'blocked' => 'in_progress', 'rework' => 'backlog'];

    /** حداکثر کارت‌های ستون «انجام‌شده» (جدیدترین‌ها) تا برد شلوغ نشود */
    private const DONE_LIMIT = 30;

    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** پروژه‌هایی که کاربر در آن‌هاست (سازنده یا عضو). */
    public static function projects(int $uid): array
    {
        return DB::all('SELECT p.id, p.title, p.profile_image FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) ORDER BY p.title', [$uid, $uid]);
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
            $where .= ' AND t.assignee_id = ?';
            $params[] = $uid;
        }
        if (($f['q'] ?? '') !== '') {
            $where .= ' AND t.title LIKE ?';
            $params[] = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
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
        $out = Access::boardTargets($t, $uid, $st, array_keys(self::STATUSES));
        if ($st === 'done' && self::canArchive($t, $uid)) {
            $out[] = 'archived';
        }
        return $out;
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

        $cols = array_fill_keys(array_keys(self::STATUSES), []);
        $archivable = 0;
        foreach ($rows as $t) {
            if (!Access::canBoardView($t, $uid)) {
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
            if (!Access::canBoardView($t, $uid)) {
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

    private static function status(array $t): string
    {
        if (!empty($t['archived_at'])) {
            return 'archived';
        }
        return isset(self::STATUSES[$t['board_status'] ?? '']) ? $t['board_status'] : ((int) $t['is_completed'] === 1 ? 'done' : 'todo');
    }

    /** وضعیت بورد تسک برای جزئیات: وضعیت فعلی، مرحله‌ی بعد، اجازه‌ی انتقال یا دلیل قفل بودن */
    public static function taskState(array $t, int $uid): array
    {
        $st = self::status($t);
        $info = self::STATUSES[$st] ?? ['label' => 'آرشیو', 'icon' => 'fa-box-archive', 'tone' => 'muted'];
        $next = self::NEXT[$st] ?? null;
        $can = $next !== null && in_array($next, Access::boardTargets($t, $uid, $st, array_keys(self::STATUSES)), true)
            && ($next !== 'done' || Access::canTask($t, $uid, 'complete'));
        // مقصدهای مجاز برای پنجره‌ی تغییر وضعیت (انجام‌شده فقط با مجوز تکمیل)
        $targets = [];
        if ($st !== 'archived') {
            foreach (Access::boardTargets($t, $uid, $st, array_keys(self::STATUSES)) as $k) {
                if ($k !== 'done' || Access::canTask($t, $uid, 'complete')) {
                    $targets[] = ['key' => $k, 'label' => self::STATUSES[$k]['label'], 'icon' => self::STATUSES[$k]['icon'], 'tone' => self::STATUSES[$k]['tone']];
                }
            }
        }
        return [
            'targets' => $targets, 'lock' => $targets ? null : self::lockReason($t, $uid, $st),
            'status' => $st, 'label' => $info['label'], 'icon' => $info['icon'], 'tone' => $info['tone'],
            'next' => $next, 'next_label' => $next !== null ? self::STATUSES[$next]['label'] : null,
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
        $from = self::STATUSES[$st]['label'] ?? 'آرشیو';
        if ($to === 'done' && !Access::canTask($t, $uid, 'complete')) {
            return 'دسترسی «تکمیل تسک» در پروژه‌ی «' . $ptitle . '» به شما داده نشده است.' . $ask;
        }
        if (Access::boardPerms($t, $uid) === []) {
            return 'در پروژه‌ی «' . $ptitle . '» هیچ دسترسی جابه‌جایی کارت در بورد به شما داده نشده است؛ فقط می‌توانید کارت را ببینید.' . $ask;
        }
        if ($to !== null && isset(self::STATUSES[$to])) {
            $perm = Access::BOARD_PERMS[Access::boardMovePerm($st, $to)]['label'] ?? '';
            return 'برای انتقال از «' . $from . '» به «' . self::STATUSES[$to]['label'] . '» دسترسی «' . $perm . '» لازم است که در پروژه‌ی «' . $ptitle . '» به شما داده نشده است.' . $ask;
        }
        return 'دسترسی‌های بورد شما در پروژه‌ی «' . $ptitle . '» اجازه‌ی جابه‌جایی کارت از مرحله‌ی «' . $from . '» را نمی‌دهد.' . $ask;
    }

    /** تغییر وضعیت بورد از جزئیات تسک (پنجره‌ی کوچک روی نشان وضعیت)؛ همان قواعد بورد، بدون نیاز به دیدن کارت در بورد */
    public static function setStatus(int $actor, int $id, string $status, string $reason = ''): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
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
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $st = self::status($t);
        if ($from !== '' && $from !== $st) {
            return self::res(false, 'وضعیت این تسک در این فاصله تغییر کرده است؛ جزئیات را دوباره باز کنید.', 'STATUS_CHANGED', 409);
        }
        $next = self::NEXT[$st] ?? null;
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
        $sel = 'SELECT t.id, t.title, t.share_token, t.user_id, t.assignee_id, t.project_id, t.blocked_reason, t.board_moved_at, p.title AS project_title,
                u.first_name, u.last_name, u.avatar FROM tasks t LEFT JOIN projects p ON p.id = t.project_id LEFT JOIN users u ON u.id = COALESCE(t.assignee_id, t.user_id)';
        $review = [];
        foreach (DB::all($sel . " WHERE t.board_status = 'review' AND t.archived_at IS NULL AND t.is_completed = 0
                AND (p.user_id = ? OR t.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)) ORDER BY t.board_moved_at DESC, t.id DESC LIMIT 200", [$uid, $uid]) as $t) {
            if (in_array('done', Access::boardTargets($t, $uid, 'review', ['done']), true) && Access::canTask($t, $uid, 'complete')) {
                $review[] = $t;
                if (count($review) >= $limit) {
                    break;
                }
            }
        }
        $rework = DB::all($sel . " WHERE t.board_status = 'rework' AND t.archived_at IS NULL AND COALESCE(t.assignee_id, t.user_id) = ?
                ORDER BY t.board_moved_at DESC, t.id DESC LIMIT " . max(1, $limit), [$uid]);
        return ['review' => $review, 'rework' => $rework];
    }

    /** انتقال کارت به ستون دیگر. */
    /** $fromTask: از تیک «انجام شد» در لیست تسک‌ها (دیدن تسک قبلاً با مجوز view بررسی شده؛ دیدن کارت در برد لازم نیست) */
    public static function move(int $actor, int $id, string $status, string $reason = '', bool $fromTask = false): array
    {
        if (!isset(self::STATUSES[$status]) && $status !== 'archived') {
            return self::res(false, 'وضعیت نامعتبر است.', 'STATUS_INVALID', 422);
        }
        $t = TaskModel::raw($id);
        if ($t === null || (!$fromTask && !Access::canBoardView($t, $actor))) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
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
            return self::res(true, 'تسک همین حالا در «' . self::STATUSES[$status]['label'] . '» است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $status === 'done']);
        }
        if ($status === 'done' && !Access::canTask($t, $actor, 'complete')) {
            return self::res(false, 'شما دسترسی لازم برای تکمیل تسک را ندارید.', 'FORBIDDEN', 403);
        }
        if (!in_array($status, Access::boardTargets($t, $actor, $from, array_keys(self::STATUSES)), true)) {
            return self::res(false, 'اجازه‌ی انتقال این تسک از «' . self::STATUSES[$from]['label'] . '» به «' . self::STATUSES[$status]['label'] . '» را ندارید.', 'FORBIDDEN', 403);
        }
        if ($err = self::limitError($t, $status)) {
            return self::res(false, $err, 'BOARD_LIMIT', 409);
        }
        // علت: برای «مسدودشده» علت توقف، برای «بازبینی مسئول» ایرادی که سرپرست دیده (همان ستون blocked_reason)
        $reason = trim(mb_substr($reason, 0, 255));
        $reason = in_array($status, ['blocked', 'rework'], true) && $reason !== '' ? $reason : null;
        $done = $status === 'done';
        DB::run('UPDATE tasks SET board_status = ?, blocked_reason = ?, board_moved_at = NOW(), archived_at = NULL, is_completed = ?,
                   completed_at = CASE WHEN ? = 1 THEN COALESCE(completed_at, NOW()) ELSE NULL END WHERE id = ?',
            [$status, $reason, $done ? 1 : 0, $done ? 1 : 0, $id]);
        TaskHistory::log($id, $actor, 'board', ['from' => $from, 'to' => $status, 'reason' => $reason]);
        if ($status === 'review' && $from !== 'done') {
            Notifier::taskInReview($t, $actor, self::reviewers($t, $actor));
        } elseif ($status === 'rework') {
            // یادداشت خودکار روی تسک با علت برگشت؛ نویسنده = کسی که تسک را برگردانده
            DB::insert('task_notes', ['task_id' => $id, 'user_id' => $actor,
                'note' => '↩️ برگشت به «' . self::STATUSES['rework']['label'] . '» برای اصلاح' . "\n" . 'علت: ' . ($reason ?? 'ذکر نشده')]);
            Notifier::taskReturned($t, $actor, $reason);
        }
        return self::res(true, 'تسک به «' . self::STATUSES[$status]['label'] . '» منتقل شد.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $done]);
    }
}
