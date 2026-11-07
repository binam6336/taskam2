<?php
declare(strict_types=1);

namespace App\Core;

/**
 * شناسه‌ی عمومی (public id) — v5.0
 *
 * شناسه‌ی عددی دیتابیس (id) هرگز بیرون از سرور دیده نمی‌شود: آدرس‌ها، فرم‌ها، JSON، API و HTML فقط «pid» دارند؛
 * یک رشته‌ی تصادفی ۱۲ نویسه‌ای از حروف کوچک و ارقام بدون نویسه‌های شبیه به هم (0/1/i/l/o) که همیشه دست‌کم یک حرف دارد
 * (پس هرگز با عدد اشتباه نمی‌شود). کلیدهای خارجی داخل دیتابیس همان id عددی می‌مانند.
 *
 *  - ورودی: پارامترهای مسیر ({task}، {project}، …) در Router و فیلدهای شناخته‌شده‌ی فرم/کوئری/JSON (INPUTS) در Request
 *    خودکار از pid به id تبدیل می‌شوند؛ مقدار عددی یا pid ناموجود پذیرفته نمی‌شود (-1 = نامعتبر).
 *  - خروجی: Pid::of(جدول، id) — با کش درون درخواست و prime() برای فهرست‌ها؛ اگر ردیفی pid نداشته باشد همان لحظه ساخته می‌شود.
 *  - ساخت ردیف: DB::insert برای جدول‌های TABLES خودش pid می‌گذارد.
 */
final class Pid
{
    public const LENGTH = 12;
    private const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    /** جدول‌هایی که شناسه‌شان بیرون دیده می‌شود (متن‌های آماده از قبل شناسه‌ی رشته‌ای تصادفی دارند) */
    public const TABLES = ['users', 'projects', 'tasks', 'task_notes', 'task_attachments', 'note_attachments', 'subjects', 'call_requests',
        'support_tickets', 'support_messages', 'chat_messages', 'api_tokens', 'project_join_requests', 'colleague_requests', 'project_members', 'colleagues', 'chat_requests', 'impersonations', 'project_groups'];

    /** نام پارامتر مسیر => جدول (config/routes.php) */
    public const ROUTE = [
        'task' => 'tasks', 'note' => 'task_notes', 'label' => 'subjects', 'call' => 'call_requests', 'user' => 'users', 'project' => 'projects',
        'ticket' => 'support_tickets', 'token' => 'api_tokens', 'projreq' => 'project_join_requests', 'colreq' => 'colleague_requests', 'imp' => 'impersonations', 'group' => 'project_groups',
    ];

    /** فیلدهای ورودی (query، فرم، JSON) که شناسه‌ی یک جدول‌اند => جدول */
    public const INPUTS = [
        'user_id' => 'users', 'assignee_id' => 'users', 'receiver_id' => 'users', 'colleague_user_id' => 'users', 'assignee' => 'users',
        'user_ids' => 'users', 'view_users' => 'users', 'view_user_ids' => 'users', 'mentions' => 'users', 'members' => 'users', 'member_ids' => 'users',
        'project_id' => 'projects', 'project' => 'projects', 'assignee_group_id' => 'project_groups', 'group_id' => 'project_groups', 'group' => 'project_groups', 'head_id' => 'users',
        'subject_id' => 'subjects', 'label' => 'subjects',
        'task_id' => 'tasks', 'message_id' => 'chat_messages', 'ticket_id' => 'support_tickets', 'call_id' => 'call_requests', 'request_id' => 'project_join_requests',
        'row_id' => 'colleagues', 'note_id' => 'task_notes', 'member_row_id' => 'project_members',
    ];

    /** کلیدهای خروجی (JSON و داده‌ی boot) که شناسه‌ی یک جدول‌اند => جدول؛ Pid::out خودکار عوضشان می‌کند (کلید id صریح در کد) */
    public const OUTPUTS = [
        'user_id' => 'users', 'assignee_id' => 'users', 'creator_id' => 'users', 'receiver_id' => 'users', 'sender_id' => 'users', 'author_id' => 'users',
        'actor_id' => 'users', 'colleague_user_id' => 'users', 'blocked_by' => 'users', 'delivered_by' => 'users', 'approved_by' => 'users', 'owner_id' => 'users',
        'target_id' => 'users', 'peer_id' => 'users', 'other_id' => 'users', 'blocked_user_id' => 'users', 'unblocked_user_id' => 'users', 'uploader_id' => 'users',
        'requester_id' => 'users', 'from_user_id' => 'users', 'to_user_id' => 'users', 'admin_id' => 'users', 'me' => 'users',
        'forwarded_from' => 'users', 'other_user_id' => 'users', 'blocker_user_id' => 'users',
        'project_id' => 'projects', 'assignee_group_id' => 'project_groups', 'group_id' => 'project_groups', 'head_id' => 'users', 'subject_id' => 'subjects', 'label_id' => 'subjects', 'task_id' => 'tasks', 'note_id' => 'task_notes',
        'message_id' => 'chat_messages', 'ticket_id' => 'support_tickets', 'call_id' => 'call_requests', 'member_row_id' => 'project_members', 'row_id' => 'colleagues',
        'request_id' => 'project_join_requests',
    ];
    /** کلیدهای خروجی که فهرست شناسه‌اند */
    public const OUTPUT_LISTS = [
        'user_ids' => 'users', 'member_ids' => 'users', 'assignee_ids' => 'users', 'board_view_user_ids' => 'users', 'my_board_view_user_ids' => 'users',
        'view_user_ids' => 'users', 'members' => 'users', 'mentioned' => 'users', 'mentions' => 'users', 'project_ids' => 'projects', 'task_ids' => 'tasks', 'ticked' => 'users',
    ];

    /** @var array<string,array<int,string>> */
    private static array $byId = [];
    /** @var array<string,array<string,int>> */
    private static array $byPid = [];

    public static function new(): string
    {
        do {
            $s = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $s .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (!preg_match('/[a-z]/', $s));
        return $s;
    }

    public static function valid(string $pid): bool
    {
        return (bool) preg_match('/^[2-9a-hjkmnp-z]{' . self::LENGTH . '}$/', $pid) && preg_match('/[a-z]/', $pid);
    }

    /** pid ← id (null = ردیف نیست). ردیف بدون pid (مثلاً درج‌شده با SQL خام) همان‌جا pid می‌گیرد. */
    public static function of(string $table, $id): ?string
    {
        $id = (int) $id;
        if ($id <= 0 || !in_array($table, self::TABLES, true)) {
            return null;
        }
        if (isset(self::$byId[$table][$id])) {
            return self::$byId[$table][$id];
        }
        $p = DB::value('SELECT pid FROM `' . $table . '` WHERE id = ?', [$id]);
        if ($p === null || $p === false) {
            if (!DB::value('SELECT 1 FROM `' . $table . '` WHERE id = ?', [$id])) {
                return null;
            }
            $p = self::assign($table, $id);
        }
        return self::remember($table, $id, (string) $p);
    }

    /** پیش‌بارگذاری pid چند ردیف (فهرست‌ها) تا of() برای هر ردیف کوئری جدا نزند */
    public static function prime(string $table, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0 && !isset(self::$byId[$table][$v]))));
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::all('SELECT id, pid FROM `' . $table . '` WHERE id IN (' . DB::in($chunk) . ')', $chunk) as $r) {
                if ($r['pid'] !== null && $r['pid'] !== '') {
                    self::remember($table, (int) $r['id'], (string) $r['pid']);
                }
            }
        }
    }

    /** id ← pid (null = نامعتبر یا ناموجود؛ مقدار عددی هرگز پذیرفته نمی‌شود) */
    public static function id(string $table, $pid): ?int
    {
        $pid = is_string($pid) ? strtolower(trim($pid)) : '';
        if (!self::valid($pid) || !in_array($table, self::TABLES, true)) {
            return null;
        }
        if (isset(self::$byPid[$table][$pid])) {
            return self::$byPid[$table][$pid];
        }
        $id = DB::value('SELECT id FROM `' . $table . '` WHERE pid = ? LIMIT 1', [$pid]);
        if ($id === null || $id === false) {
            return null;
        }
        self::remember($table, (int) $id, $pid);
        return (int) $id;
    }

    /** چند pid ← آرایه‌ی id (ترتیب حفظ می‌شود؛ ناموجودها حذف) */
    public static function many(string $table, array $ids): array
    {
        self::prime($table, $ids);
        return array_values(array_filter(array_map(static fn($i) => self::of($table, $i), $ids)));
    }

    /** ورودی‌های شناخته‌شده‌ی یک آرایه (query/فرم/JSON) را از pid به id تبدیل می‌کند؛ نامعتبر = -1، خالی دست نمی‌خورد */
    public static function translateInput(array $in): array
    {
        foreach (self::INPUTS as $key => $table) {
            if (!array_key_exists($key, $in)) {
                continue;
            }
            $v = $in[$key];
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $x) {
                    if (is_scalar($x) && ($id = self::id($table, (string) $x)) !== null) {
                        $out[$k] = (string) $id;
                    }
                }
                $in[$key] = array_values($out);
            } elseif (is_scalar($v) && trim((string) $v) !== '' && (string) $v !== 'all') {
                $id = self::id($table, (string) $v);
                $in[$key] = $id === null ? '-1' : (string) $id;
            }
        }
        return $in;
    }

    /** pid یک ردیف (اگر ستون pid در SELECT بوده همان، وگرنه از روی id) */
    public static function row(string $table, ?array $r): ?string
    {
        if (!$r) {
            return null;
        }
        return !empty($r['pid']) ? (string) $r['pid'] : self::of($table, $r['id'] ?? 0);
    }

    /** کلید id (و اختیاری کلیدهای دیگر) یک آرایه‌ی خروجی را با pid جدول داده‌شده عوض می‌کند */
    public static function tag(array $data, string $table, string $key = 'id'): array
    {
        if (isset($data[$key]) && self::isNum($data[$key])) {
            $data[$key] = self::of($table, $data[$key]);
        }
        return $data;
    }

    /** فهرست pid ورودی ← فهرست id (نامعتبرها حذف) */
    public static function ids(string $table, $list): array
    {
        $out = [];
        foreach ((array) $list as $x) {
            if (is_scalar($x) && ($id = self::id($table, (string) $x)) !== null) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * تور ایمنی خروجی: در آرایه‌ی JSON/boot، مقدار عددیِ کلیدهای OUTPUTS/OUTPUT_LISTS را با pid عوض می‌کند (بازگشتی).
     * مقدار غیرعددی (pid که از قبل گذاشته شده) دست نمی‌خورد. کلید «id» این‌جا ترجمه نمی‌شود چون جدولش از کلید معلوم نیست.
     */
    public static function out($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $want = [];
        self::collect($data, $want);
        if (!empty($GLOBALS['__pid_leak']) || filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            self::leaks($data, '');
        }
        foreach ($want as $t => $ids) {
            self::prime($t, array_keys($ids));
        }
        return self::swap($data);
    }

    /** حالت دیباگ: کلید id عددی که هنوز ترجمه نشده در storage/logs/pid-leak.log ثبت می‌شود */
    private static function leaks(array $a, string $path): void
    {
        foreach ($a as $k => $v) {
            if ($k === 'id' && self::isNum($v) && !isset($a['pid'])) {
                @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/pid-leak.log', date('c') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ' . $path . '.id=' . $v . "\n", FILE_APPEND);
            } elseif (is_array($v)) {
                self::leaks($v, $path . '.' . $k);
            }
        }
    }

    private static function isNum($v): bool
    {
        return is_int($v) || (is_string($v) && $v !== '' && ctype_digit($v));
    }

    private static function collect(array $a, array &$want): void
    {
        foreach ($a as $k => $v) {
            if (is_string($k) && isset(self::OUTPUTS[$k]) && self::isNum($v)) {
                $want[self::OUTPUTS[$k]][(int) $v] = true;
            } elseif (is_string($k) && isset(self::OUTPUT_LISTS[$k]) && is_array($v)) {
                foreach ($v as $x) {
                    if (self::isNum($x)) {
                        $want[self::OUTPUT_LISTS[$k]][(int) $x] = true;
                    } elseif (is_array($x)) {
                        self::collect($x, $want);
                    }
                }
            } elseif (is_array($v)) {
                self::collect($v, $want);
            }
        }
    }

    private static function swap(array $a): array
    {
        // ردیف خام دیتابیس که ستون pid هم دارد: id همان pid می‌شود
        if (isset($a['pid'], $a['id']) && is_string($a['pid']) && self::valid($a['pid']) && self::isNum($a['id'])) {
            $a['id'] = $a['pid'];
            unset($a['pid']);
        }
        foreach ($a as $k => $v) {
            if (is_string($k) && isset(self::OUTPUTS[$k]) && self::isNum($v)) {
                $a[$k] = (int) $v > 0 ? self::of(self::OUTPUTS[$k], $v) : null;
            } elseif (is_string($k) && isset(self::OUTPUT_LISTS[$k]) && is_array($v)) {
                $list = [];
                $isList = array_keys($v) === range(0, count($v) - 1);
                foreach ($v as $i => $x) {
                    if (self::isNum($x)) {
                        if (($p = self::of(self::OUTPUT_LISTS[$k], $x)) !== null) {
                            $list[] = $p;
                        }
                    } else {
                        $x = is_array($x) ? self::swap($x) : $x;
                        $isList ? $list[] = $x : $list[$i] = $x;
                    }
                }
                $a[$k] = $list;
            } elseif (is_array($v)) {
                $a[$k] = self::swap($v);
            }
        }
        return $a;
    }

    private static function remember(string $table, int $id, string $pid): string
    {
        self::$byId[$table][$id] = $pid;
        self::$byPid[$table][$pid] = $id;
        return $pid;
    }

    private static function assign(string $table, int $id): string
    {
        for ($i = 0; $i < 5; $i++) {
            $p = self::new();
            try {
                if (DB::run('UPDATE `' . $table . '` SET pid = ? WHERE id = ? AND pid IS NULL', [$p, $id]) > 0) {
                    return $p;
                }
                return (string) DB::value('SELECT pid FROM `' . $table . '` WHERE id = ?', [$id]);
            } catch (\PDOException $e) {
                // برخورد (بسیار نادر) با UNIQUE: دوباره
            }
        }
        throw new \RuntimeException('pid assign failed');
    }

    /** پر کردن pid ردیف‌های قدیمی (Schema::ensure و php bin/migrate.php) */
    public static function backfill(?callable $say = null): int
    {
        $n = 0;
        foreach (self::TABLES as $t) {
            while ($ids = DB::column('SELECT id FROM `' . $t . '` WHERE pid IS NULL LIMIT 500')) {
                foreach ($ids as $id) {
                    self::assign($t, (int) $id);
                    $n++;
                }
            }
        }
        if ($n && $say) {
            $say('✓ شناسه‌ی عمومی برای ' . $n . ' ردیف ساخته شد');
        }
        return $n;
    }
}
