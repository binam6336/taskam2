<?php
declare(strict_types=1);

namespace App\Core;

/**
 * لاگ امنیتی مدیران (v5.2) — پنل مدیر ← تنظیمات ← امنیت.
 * فعلاً ورود مدیر به پنل کاربر: ورود (enter)، خروج (exit، با دلیل) و هر تغییری که مدیر داخل پنجره‌ی پنل کاربر داده است.
 *
 * ثبت تغییرها در لایه‌ی DB انجام می‌شود (نه در تک‌تک سرویس‌ها) تا هیچ تغییری از قلم نیفتد: داخل پنجره‌ی ورود به پنل کاربر
 * (Impersonation::boot ← AdminLog::watch) هر INSERT / UPDATE / DELETE روی جدول‌های TRACK با عکس ردیف قبل و بعد ثبت می‌شود
 * و فقط ستون‌هایی که واقعاً عوض شده‌اند (منهای ستون‌های فنی SKIP) با مقدار قبل و بعد ذخیره می‌شوند.
 * خطای ثبت لاگ هرگز جلوی کار اصلی را نمی‌گیرد.
 */
final class AdminLog
{
    /** جدول ← [نام فارسی، ستون عنوان] */
    public const TRACK = [
        'tasks' => ['تسک', 'title'], 'projects' => ['پروژه', 'title'], 'subjects' => ['لیبل', 'title'], 'call_requests' => ['درخواست تماس', null],
        'task_notes' => ['یادداشت تسک', null], 'task_attachments' => ['پیوست تسک', 'file_name'], 'note_attachments' => ['پیوست یادداشت', 'file_name'],
        'project_members' => ['عضویت پروژه', null], 'project_join_requests' => ['دعوت به پروژه', null], 'project_blocks' => ['مسدودی دعوت پروژه', null],
        'project_leave_history' => ['خروج از پروژه', null], 'colleagues' => ['همکار', null], 'colleague_requests' => ['درخواست همکاری', null],
        'colleague_blocks' => ['مسدودی کاربر', null], 'colleague_block_ghosts' => ['مسدودی کاربر', null], 'chat_messages' => ['پیام گفتگو', null],
        'chat_requests' => ['درخواست گفتگو', null], 'chat_history_clears' => ['پاک کردن تاریخچه‌ی گفتگو', null], 'support_tickets' => ['تیکت پشتیبانی', 'subject'],
        'support_messages' => ['پیام تیکت', null], 'texts' => ['متن آماده', 'title'], 'users' => ['پروفایل کاربر', null],
        'project_groups' => ['گروه / دپارتمان', 'title'], 'project_group_members' => ['عضویت گروه', null], 'task_group_ticks' => ['تیک عضو گروه روی تسک', null], 'task_group_duties' => ['وظیفه‌ی شخصی عضو گروه', 'duty'],
    ];

    /** ستون‌های فنی یا محرمانه که در لاگ نمی‌آیند */
    private const SKIP = ['id', 'pid', 'updated_at', 'created_at', 'last_active_at', 'last_seen', 'notified_at', 'share_token', 'token_hash', 'password_hash',
        'file_path', 'file_mime', 'attachment', 'message', 'attachment_name', 'board_moved_at', 'last_reply_at', 'is_read_by_user', 'is_read_by_admin', 'quick_links'];

    private static bool $watch = false;
    private static bool $busy = false;
    private static ?string $req = null;

    public static function watch(bool $on): void
    {
        self::$watch = $on;
    }

    public static function watching(): bool
    {
        return self::$watch && !self::$busy;
    }

    /** ورود مدیر به پنل کاربر */
    public static function enter(array $admin, array $target, int $impId, string $ip): void
    {
        self::write(['admin_id' => (int) $admin['id'], 'admin_role' => Auth::adminRole($admin), 'target_id' => (int) $target['id'], 'impersonation_id' => $impId,
            'action' => 'enter', 'ip' => $ip]);
    }

    /** خروج / بسته شدن پنجره (با دلیل) */
    public static function exit(int $adminId, int $targetId, int $impId, string $reason): void
    {
        $adm = DB::one('SELECT id, role, admin_role FROM users WHERE id = ?', [$adminId]);
        self::write(['admin_id' => $adminId, 'admin_role' => $adm ? Auth::adminRole($adm) : null, 'target_id' => $targetId, 'impersonation_id' => $impId,
            'action' => 'exit', 'changes' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * DB::run در حالت watch: عکس ردیف‌ها قبل و بعد از دستور تغییر‌دهنده و ثبت تفاوت‌ها.
     * @param callable $exec اجرای خود دستور (تعداد ردیف‌های متأثر)
     */
    public static function capture(string $sql, array $params, callable $exec): int
    {
        $plan = null;
        try {
            $plan = self::plan($sql, $params);
        } catch (\Throwable $e) {
            $plan = null;
        }
        if ($plan === null) {
            return $exec();
        }
        [$op, $table] = $plan;
        if ($op === 'insert') {
            $n = $exec();
            $id = (int) DB::pdo()->lastInsertId();
            if ($n > 0 && $id > 0) {
                self::inserted($table, $id);
            } elseif ($n > 0 && $table === 'chat_history_clears' && ($c = Impersonation::current())) {
                $row = DB::one('SELECT * FROM chat_history_clears WHERE user_id = ? ORDER BY cleared_at DESC LIMIT 1', [(int) $c['target_id']]);
                if ($row) {
                    self::change('create', $table, $row, [], null);
                }
            }
            return $n;
        }
        $before = [];
        try {
            $before = DB::all('SELECT * FROM `' . $table . '`' . $plan[2] . ' WHERE ' . $plan[3] . ' LIMIT 200', $plan[4]);
        } catch (\Throwable $e) {
            $before = [];
        }
        $n = $exec();
        if (!$before || $n === 0) {
            return $n;
        }
        try {
            if ($op === 'delete') {
                foreach ($before as $row) {
                    self::change('delete', $table, $row, array_map(static fn($k) => ['f' => $k, 'b' => $row[$k]], self::cols($table, $row)), $row);
                }
                return $n;
            }
            $ids = array_values(array_filter(array_map(static fn($r) => $r['id'] ?? null, $before), static fn($v) => $v !== null));
            if (!$ids) {
                return $n;
            }
            $after = [];
            foreach (DB::all('SELECT * FROM `' . $table . '` WHERE id IN (' . DB::in($ids) . ')', $ids) as $r) {
                $after[(string) $r['id']] = $r;
            }
            foreach ($before as $b) {
                $a = $after[(string) ($b['id'] ?? '')] ?? null;
                if ($a === null) {
                    continue;
                }
                $diff = [];
                foreach (self::cols($table, $a) as $k) {
                    if ((string) ($b[$k] ?? '') !== (string) ($a[$k] ?? '') || (($b[$k] ?? null) === null) !== (($a[$k] ?? null) === null)) {
                        $diff[] = ['f' => $k, 'b' => $b[$k] ?? null, 'a' => $a[$k] ?? null];
                    }
                }
                if ($diff) {
                    self::change('update', $table, $a, $diff, $b);
                }
            }
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
        return $n;
    }

    /** DB::insert در حالت watch */
    public static function inserted(string $table, int $id): void
    {
        if (!self::watching() || !isset(self::TRACK[$table])) {
            return;
        }
        try {
            $row = DB::one('SELECT * FROM `' . $table . '` WHERE id = ?', [$id]);
            if ($row === null || ($table === 'chat_messages' && (int) DB::value('SELECT is_bot FROM users WHERE id = ?', [(int) $row['sender_id']]) === 1)) {
                return;                                   // پیام‌های خودکار ربات اطلاع‌رسانی (نتیجه‌ی جانبی) ثبت نمی‌شوند
            }
            $ch = [];
            foreach (self::cols($table, $row) as $k) {
                if ($row[$k] !== null && $row[$k] !== '') {
                    $ch[] = ['f' => $k, 'a' => $row[$k]];
                }
            }
            self::change('create', $table, $row, $ch, null);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }

    /** [op, table, alias, where, whereParams] یا null (دستور خواندنی / جدول ثبت‌نشدنی / ساختار ناشناخته) */
    private static function plan(string $sql, array $params): ?array
    {
        $s = ltrim($sql);
        if (preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $s, $m)) {
            return isset(self::TRACK[$m[1]]) ? ['insert', $m[1]] : null;
        }
        if (preg_match('/^UPDATE\s+`?(\w+)`?(\s+(?!SET\b)[a-z]\w*)?\s+SET\s+/i', $s, $m)) {
            $op = 'update';
        } elseif (preg_match('/^DELETE\s+FROM\s+`?(\w+)`?(\s+(?!WHERE\b)[a-z]\w*)?\s+/i', $s, $m)) {
            $op = 'delete';
        } else {
            return null;
        }
        $table = $m[1];
        if (!isset(self::TRACK[$table])) {
            return null;
        }
        $rest = substr($s, strlen($m[0]));
        $pos = self::topWhere($rest);
        if ($pos === null) {
            return null;                                  // بدون WHERE (یا ساختار ناشناخته): بدون عکس قبل/بعد
        }
        $head = substr($rest, 0, $pos);
        $where = trim(substr($rest, $pos + 5));
        $wp = array_slice(array_values($params), substr_count($head, '?'));
        return [$op, $table, isset($m[2]) ? ' ' . trim($m[2]) : '', $where, $wp];
    }

    /** محل WHERE سطح اول (بیرون از پرانتز و رشته) */
    private static function topWhere(string $s): ?int
    {
        $depth = 0;
        $q = null;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($q !== null) {
                if ($c === $q) {
                    $q = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $q = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($depth === 0 && ($c === 'W' || $c === 'w') && preg_match('/\GWHERE\s/i', $s, $mm, 0, $i) && ($i === 0 || !ctype_alnum($s[$i - 1]) && $s[$i - 1] !== '_')) {
                return $i;
            }
        }
        return null;
    }

    private static function cols(string $table, array $row): array
    {
        $skip = self::SKIP;
        return array_values(array_filter(array_keys($row), static fn($k) => !in_array($k, $skip, true)));
    }

    private static function change(string $op, string $table, array $row, array $changes, ?array $before): void
    {
        if (!$changes && $op === 'update' && $table !== 'chat_history_clears') {
            return;
        }
        $c = Impersonation::current();
        if ($c === null) {
            return;
        }
        self::write([
            'admin_id' => (int) $c['admin_id'], 'admin_role' => Auth::adminRole($c['admin']), 'target_id' => (int) $c['target_id'], 'impersonation_id' => (int) $c['id'],
            'action' => $op, 'entity' => $table, 'entity_id' => isset($row['id']) ? (string) $row['id'] : null, 'entity_title' => mb_substr(self::title($table, $row + ($before ?? [])), 0, 250),
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 'path' => mb_substr(Request::capture()->path(), 0, 190),
        ]);
    }

    private static function title(string $table, array $r): string
    {
        $name = static function ($uid): string {
            $u = $uid ? DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [(int) $uid]) : null;
            return $u ? full_name($u) : 'کاربر';
        };
        $col = self::TRACK[$table][1];
        if ($col !== null && isset($r[$col]) && $r[$col] !== '') {
            return (string) $r[$col];
        }
        switch ($table) {
            case 'call_requests':
                return trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            case 'task_notes':
                return (string) DB::value('SELECT title FROM tasks WHERE id = ?', [(int) ($r['task_id'] ?? 0)]);
            case 'project_members':
            case 'project_join_requests':
            case 'project_leave_history':
            case 'project_blocks':
                return $name($r['user_id'] ?? 0) . ' — ' . (string) DB::value('SELECT title FROM projects WHERE id = ?', [(int) ($r['project_id'] ?? 0)]);
            case 'colleagues':
                return $name($r['colleague_user_id'] ?? 0);
            case 'colleague_requests':
            case 'chat_requests':
                return $name($r['target_id'] ?? 0);
            case 'colleague_blocks':
                return $name($r['blocked_user_id'] ?? 0);
            case 'chat_messages':
                return 'به ' . $name($r['receiver_id'] ?? 0);
            case 'chat_history_clears':
                return 'گفتگو با ' . $name($r['other_user_id'] ?? 0);
            case 'support_messages':
                return (string) DB::value('SELECT subject FROM support_tickets WHERE id = ?', [(int) ($r['ticket_id'] ?? 0)]);
            case 'users':
                return full_name($r);
        }
        return '';
    }

    private static function write(array $row): void
    {
        if (self::$busy) {
            return;
        }
        self::$busy = true;
        try {
            self::$req ??= bin2hex(random_bytes(8));
            $row += ['request_key' => self::$req, 'ip' => Request::capture()->ip(), 'created_at' => date('Y-m-d H:i:s')];
            DB::insert('admin_logs', $row);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        } finally {
            self::$busy = false;
        }
    }

    // ───────────────────── نمایش (صفحه‌ی «امنیت») ─────────────────────

    /** نام فارسی ستون‌ها */
    private const LABELS = [
        'title' => 'عنوان', 'description' => 'توضیحات', 'priority' => 'اولویت', 'priority_percent' => 'درصد اولویت', 'due_date' => 'تاریخ انجام', 'due_time' => 'ساعت انجام',
        'assignee_id' => 'مسئول', 'assignee_group_id' => 'گروه مسئول', 'group_head_approval' => 'تحویل با تیک سرگروه', 'head_id' => 'سرگروه', 'group_id' => 'گروه', 'project_id' => 'پروژه', 'subject_id' => 'لیبل', 'board_status' => 'وضعیت بورد', 'is_completed' => 'انجام‌شده', 'completed_at' => 'زمان انجام',
        'delivered_at' => 'زمان تحویل برای بررسی', 'delivered_by' => 'تحویل‌دهنده', 'approved_by' => 'تأییدکننده', 'blocked_at' => 'زمان مسدود شدن', 'blocked_by' => 'مسدودکننده',
        'blocked_reason' => 'دلیل برگشت برای اصلاح', 'archived_at' => 'زمان آرشیو', 'is_quick' => 'ثبت سریع', 'note' => 'متن یادداشت', 'color' => 'رنگ',
        'first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'mobile' => 'موبایل', 'email' => 'ایمیل', 'store' => 'فروشگاه', 'website' => 'وب‌سایت', 'status' => 'وضعیت',
        'max_members' => 'سقف اعضا', 'priority_type' => 'نوع اولویت', 'profile_image' => 'تصویر پروژه', 'permissions' => 'دسترسی‌های پروژه', 'task_permissions' => 'دسترسی تسک‌ها',
        'board_permissions' => 'دسترسی بورد', 'board_view_users' => 'مشاهده‌ی تسک‌های اعضا', 'board_limits' => 'سقف تعداد تسک ستون‌ها', 'user_id' => 'کاربر', 'colleague_user_id' => 'همکار',
        'theme' => 'تم ظاهری', 'tour' => 'تور آموزشی', 'board_order' => 'ترتیب ستون‌های بورد', 'file_name' => 'نام فایل', 'file_size' => 'حجم فایل', 'file_ext' => 'نوع فایل',
        'content' => 'متن', 'sort_order' => 'ترتیب', 'subject' => 'موضوع', 'task_id' => 'تسک', 'note_id' => 'یادداشت', 'invited_by' => 'دعوت‌کننده', 'requester_id' => 'درخواست‌دهنده',
        'target_id' => 'گیرنده', 'sender_id' => 'فرستنده', 'receiver_id' => 'گیرنده', 'blocked_user_id' => 'کاربر مسدودشده', 'blocker_user_id' => 'مسدودکننده',
        'is_edited' => 'ویرایش‌شده', 'edited_at' => 'زمان ویرایش', 'is_read' => 'خوانده‌شده', 'read_at' => 'زمان خواندن', 'forwarded_from' => 'بازارسال از', 'responded_at' => 'زمان پاسخ',
        'task_link_access' => 'دسترسی لینک تسک', 'avatar' => 'تصویر پروفایل', 'other_user_id' => 'طرف گفتگو', 'cleared_at' => 'زمان پاک کردن', 'cleared_upto_id' => 'تا پیام',
        'added_at' => 'زمان عضویت', 'left_at' => 'زمان خروج', 'closed_at' => 'زمان بستن', 'sender_role' => 'نقش فرستنده', 'attachment_size' => 'حجم پیوست', 'attachment_type' => 'نوع پیوست',
    ];
    private const USER_COLS = ['assignee_id', 'head_id', 'created_by', 'delivered_by', 'approved_by', 'blocked_by', 'user_id', 'colleague_user_id', 'invited_by', 'requester_id', 'target_id', 'sender_id',
        'receiver_id', 'blocked_user_id', 'blocker_user_id', 'forwarded_from', 'other_user_id'];
    public const REASONS = [
        'exit' => 'خودش پنجره را بست', 'admin_end' => 'بستن از پنل مدیر', 'end_all' => '«بستن همه» در پنل مدیر', 'logout' => 'خروج مدیر از پنل',
        'idle' => 'بی‌فعالیتی', 'expired' => 'پایان زمان مجاز', 'admin_changed' => 'تغییر دسترسی یا رمز مدیر', 'target_inactive' => 'غیرفعال شدن کاربر',
        'misuse' => 'استفاده در نشست یا مرورگر دیگر', 'limit' => 'سقف پنجره‌های باز',
    ];
    private const VERBS = ['create' => 'ایجاد کرد', 'update' => 'تغییر داد', 'delete' => 'حذف کرد'];

    /** ردیف‌های admin_logs ← ساختار نمایش (متن فارسی، پروفایل‌ها، تغییرها با قبل و بعد، لینک ورود به پنل) */
    public static function present(array $rows): array
    {
        $uids = [];
        foreach ($rows as $r) {
            $uids[] = (int) $r['admin_id'];
            $uids[] = (int) $r['target_id'];
            foreach ((array) json_decode((string) $r['changes'], true) as $c) {
                if (is_array($c) && in_array($c['f'] ?? '', self::USER_COLS, true)) {
                    foreach (['b', 'a'] as $k) {
                        if (!empty($c[$k])) {
                            $uids[] = (int) $c[$k];
                        }
                    }
                }
            }
        }
        $uids = array_values(array_unique(array_filter($uids)));
        $users = [];
        if ($uids) {
            foreach (DB::all('SELECT id, pid, first_name, last_name, mobile, avatar, role, admin_role, status FROM users WHERE id IN (' . DB::in($uids) . ')', $uids) as $u) {
                $users[(int) $u['id']] = $u;
            }
        }
        $person = static function (int $id) use ($users): ?array {
            $u = $users[$id] ?? null;
            return $u ? ['id' => pid('users', $u), 'name' => full_name($u), 'mobile' => $u['mobile'], 'avatar' => avatar_url($u['avatar']), 'is_admin' => $u['role'] === 'admin'] : null;
        };
        $out = [];
        foreach ($rows as $r) {
            $role = Auth::ADMIN_ROLES[$r['admin_role'] ?? '']['label'] ?? 'مدیر';
            $adm = $person((int) $r['admin_id']);
            $tgt = $person((int) $r['target_id']);
            $item = ['role' => $role, 'admin' => $adm, 'target' => $tgt, 'action' => $r['action'], 'at' => jdate((string) $r['created_at']),
                'at_full' => self::fullDate((string) $r['created_at']), 'changes' => [], 'entity' => null, 'link' => null, 'reason' => null];
            $ch = (array) json_decode((string) $r['changes'], true);
            if ($r['action'] === 'exit') {
                $item['reason'] = self::REASONS[$ch['reason'] ?? ''] ?? ($ch['reason'] ?? '');
            } elseif (isset(self::VERBS[$r['action']])) {
                $item['verb'] = self::VERBS[$r['action']];
                $item['entity'] = ['type' => self::TRACK[$r['entity']][0] ?? (string) $r['entity'], 'title' => (string) $r['entity_title']];
                $item['link'] = self::link((string) $r['entity'], (int) $r['entity_id'], $r['action'] === 'delete');
                foreach ($ch as $c) {
                    if (!is_array($c) || !isset($c['f'])) {
                        continue;
                    }
                    $item['changes'][] = ['label' => self::LABELS[$c['f']] ?? $c['f'],
                        'before' => array_key_exists('b', $c) ? self::value((string) $c['f'], $c['b'], $users) : null,
                        'after' => array_key_exists('a', $c) ? self::value((string) $c['f'], $c['a'], $users) : null];
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    /** «ورود به پنل»: تسک ← پنل سازنده‌ی پروژه (یا سازنده‌ی تسک) و همان تسک؛ پروژه ← پنل سازنده و کارت پروژه */
    private static function link(string $table, int $id, bool $deleted): ?array
    {
        if ($deleted || $id <= 0) {
            return null;
        }
        if ($table === 'task_notes' || $table === 'task_attachments') {
            $id = (int) DB::value('SELECT task_id FROM `' . $table . '` WHERE id = ?', [$id]);
            $table = 'tasks';
        }
        if ($table === 'tasks') {
            $t = DB::one('SELECT t.id, t.user_id, t.share_token, p.user_id AS owner FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE t.id = ?', [$id]);
            if ($t === null) {
                return null;
            }
            $tok = $t['share_token'] ?: \App\Models\TaskModel::ensureShareToken((int) $t['id']);
            return self::enterable((int) ($t['owner'] ?: $t['user_id']), '/tasks?task=' . $tok, 'باز کردن تسک در پنل مدیر پروژه');
        }
        if ($table === 'projects') {
            $owner = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$id]);
            return self::enterable($owner, '/projects?focus=' . pid('projects', $id), 'باز کردن پروژه در پنل سازنده‌ی آن');
        }
        return null;
    }

    private static function enterable(int $uid, string $to, string $title): ?array
    {
        $u = DB::one('SELECT id, pid, role, status, is_bot FROM users WHERE id = ?', [$uid]);
        if ($u === null || $u['role'] === 'admin' || $u['status'] !== 'active' || (int) $u['is_bot'] === 1) {
            return null;
        }
        return ['user' => pid('users', $u), 'to' => $to, 'title' => $title];
    }

    /** مقدار خوانا: کاربر، پروژه، لیبل، وضعیت بورد، اولویت، بله/خیر، تاریخ */
    private static function value(string $f, $v, array $users): string
    {
        if ($v === null || $v === '') {
            return '— خالی —';
        }
        if (in_array($f, self::USER_COLS, true)) {
            $u = $users[(int) $v] ?? DB::one('SELECT first_name, last_name, mobile FROM users WHERE id = ?', [(int) $v]);
            return $u ? full_name($u) . ' (' . $u['mobile'] . ')' : 'کاربر حذف‌شده';
        }
        if (in_array($f, ['permissions', 'task_permissions', 'board_permissions', 'board_view_users'], true)) {
            if ($v === 'all') {
                return 'همه‌ی اعضا';
            }
            $list = json_decode((string) $v, true);
            if (is_array($list)) {
                if (!$list) {
                    return '— هیچ —';
                }
                if ($f === 'board_view_users') {
                    return implode('، ', array_map(static fn($i) => ($u = $users[(int) $i] ?? DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [(int) $i])) ? full_name($u) : 'کاربر', $list));
                }
                $defs = ['permissions' => \App\Services\Access::PROJECT_PERMS, 'task_permissions' => \App\Services\Access::TASK_PERMS, 'board_permissions' => \App\Services\Access::BOARD_PERMS][$f];
                return implode('، ', array_map(static fn($k) => is_array($defs[$k] ?? null) ? ($defs[$k]['label'] ?? $defs[$k][0] ?? $k) : ($defs[$k] ?? $k), $list));
            }
        }
        switch ($f) {
            case 'project_id':
                return (string) (DB::value('SELECT title FROM projects WHERE id = ?', [(int) $v]) ?? 'پروژه‌ی حذف‌شده');
            case 'subject_id':
                return (string) (DB::value('SELECT title FROM subjects WHERE id = ?', [(int) $v]) ?? 'لیبل حذف‌شده');
            case 'task_id':
                return (string) (DB::value('SELECT title FROM tasks WHERE id = ?', [(int) $v]) ?? 'تسک حذف‌شده');
            case 'board_status':
                return \App\Services\BoardService::STATUSES[$v]['label'] ?? (string) $v;
            case 'priority':
                return ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'][$v] ?? (string) $v;
            case 'priority_type':
                return $v === 'percent' ? 'درصدی' : 'دسته‌ای';
            case 'file_size':
            case 'attachment_size':
                return human_size((int) $v);
            case 'due_date':
                return jdate($v . ' 00:00:00', false);
        }
        if (str_starts_with($f, 'is_')) {
            return (int) $v === 1 ? 'بله' : 'خیر';
        }
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $v)) {
            return self::fullDate((string) $v);
        }
        $s = (string) $v;
        $j = $s !== '' && ($s[0] === '[' || $s[0] === '{') ? json_decode($s, true) : null;
        if (is_array($j)) {
            $s = implode('، ', array_map(static fn($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $j)) ?: '— خالی —';
        }
        return mb_strlen($s) > 400 ? mb_substr($s, 0, 400) . '…' : $s;
    }

    /** تاریخ شمسی کامل با ساعت، دقیقه و ثانیه */
    public static function fullDate(string $dt): string
    {
        $t = strtotime($dt);
        return $t ? jdate(date('Y-m-d', $t) . ' 00:00:00', false) . ' — ساعت ' . fa_num(date('H:i:s', $t)) : $dt;
    }
}
