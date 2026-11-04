<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class ColleagueService
{
    public static function listFor(int $uid, string $q = '', int $limit = 200, int $offset = 0): array
    {
        $w = "c.user_id = ? AND u.status = 'active'";
        $p = [$uid];
        if ($q !== '') {
            $w .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.mobile LIKE ?)';
            $l = DB::like($q);
            array_push($p, $l, $l, $l);
        }
        $rows = DB::all("SELECT c.id AS row_id, c.permissions, c.created_at AS added_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.email, u.avatar,
              (SELECT COUNT(*) FROM colleague_blocks cb WHERE cb.blocker_user_id = ? AND cb.blocked_user_id = u.id) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM colleague_blocks cb WHERE cb.blocker_user_id = u.id AND cb.blocked_user_id = ?) AS has_blocked_me
            FROM colleagues c INNER JOIN users u ON u.id = c.colleague_user_id WHERE $w ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?", array_merge([$uid, $uid], $p, [$limit, $offset]));
        $ok = self::approvedIds($uid);
        foreach ($rows as &$r) {
            $r['is_blocked_by_me'] = (int) $r['is_blocked_by_me'] > 0;
            $r['has_blocked_me'] = (int) $r['has_blocked_me'] > 0;
            // همکاری‌های قدیمیِ یک‌طرفه (بدون تأیید طرف مقابل): ایمیل پنهان و موبایل ماسک
            $r['approved'] = isset($ok[(int) $r['user_id']]);
            if (!$r['approved']) {
                $r['mobile'] = mask_mobile($r['mobile']);
                $r['email'] = null;
            }
        }
        return $rows;
    }

    /**
     * همکارانی که طرف مقابل رضایت داده: درخواست همکاری را پذیرفته یا خودش هم این کاربر را همکار کرده.
     * @return array<int,true>
     */
    public static function approvedIds(int $uid): array
    {
        $ids = DB::all("SELECT target_id AS id FROM colleague_requests WHERE requester_id = ? AND status = 'accepted'
            UNION SELECT user_id AS id FROM colleagues WHERE colleague_user_id = ?", [$uid, $uid]);
        return array_fill_keys(array_map(static fn($r) => (int) $r['id'], $ids), true);
    }

    /**
     * کسانی که این کاربر را همکار خود کرده‌اند (ردیف colleagues با colleague_user_id = من).
     * mutual = من هم آن‌ها را همکار کرده‌ام؛ approved = همکاری با رضایت من (درخواستش را پذیرفته‌ام یا خودم هم اضافه‌اش کرده‌ام).
     */
    public static function followers(int $uid): array
    {
        $rows = DB::all("SELECT c.id AS row_id, c.created_at AS added_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar,
              (SELECT COUNT(*) FROM colleagues m WHERE m.user_id = ? AND m.colleague_user_id = u.id) AS mutual,
              (SELECT COUNT(*) FROM colleague_requests r WHERE r.requester_id = u.id AND r.target_id = ? AND r.status = 'accepted') AS accepted,
              (SELECT COUNT(*) FROM colleague_requests r WHERE r.requester_id = ? AND r.target_id = u.id AND r.status = 'pending') AS my_pending,
              (SELECT COUNT(*) FROM colleague_blocks cb WHERE cb.blocker_user_id = ? AND cb.blocked_user_id = u.id) AS is_blocked_by_me
            FROM colleagues c INNER JOIN users u ON u.id = c.user_id
            WHERE c.colleague_user_id = ? AND u.status = 'active' AND u.is_bot = 0 ORDER BY c.created_at DESC, c.id DESC", [$uid, $uid, $uid, $uid, $uid]);
        foreach ($rows as &$r) {
            $r['mutual'] = (int) $r['mutual'] > 0;
            $r['approved'] = $r['mutual'] || (int) $r['accepted'] > 0;
            $r['my_pending'] = (int) $r['my_pending'] > 0;
            $r['is_blocked_by_me'] = (int) $r['is_blocked_by_me'] > 0;
            if (!$r['approved']) {
                $r['mobile'] = mask_mobile($r['mobile']);      // همکاری یک‌طرفه‌ی قدیمی بدون رضایت من
            }
        }
        return $rows;
    }

    /**
     * حذف خودم از لیست همکاران یک نفر (لغو همکاری‌ای که او با من دارد).
     * دسترسی‌هایی که از همکاری می‌آید (واگذاری تسک، دیدن موبایل کامل، افزودن من به پروژه) از او گرفته می‌شود؛ عضویت‌های فعلی پروژه دست نمی‌خورد.
     */
    public static function removeFollower(int $uid, int $followerId): array
    {
        $n = DB::run('DELETE FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$followerId, $uid]);
        if ($n === 0) {
            return [false, 'این کاربر شما را در لیست همکارانش ندارد.'];
        }
        DB::run("DELETE FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = 'accepted'", [$followerId, $uid]);
        return [true, 'از لیست همکاران این کاربر خارج شدید.'];
    }

    public static function incomingRequests(int $uid): array
    {
        return DB::all("SELECT r.id, r.created_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar FROM colleague_requests r INNER JOIN users u ON u.id = r.requester_id WHERE r.target_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC", [$uid]);
    }

    public static function outgoingRequests(int $uid): array
    {
        return DB::all("SELECT r.id, r.created_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar FROM colleague_requests r INNER JOIN users u ON u.id = r.target_id WHERE r.requester_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC", [$uid]);
    }

    /**
     * فهرست مسدودشده‌ها. اطلاعات کاربر فقط وقتی نمایش داده می‌شود که همکار باشد (من او را همکار کرده‌ام یا او مرا)؛
     * موبایل کامل و ایمیل فقط برای همکاری تأییدشده، وگرنه موبایل ماسک. برای بقیه نام/آواتار/موبایل پنهان است
     * (مسدود کردن با شناسه‌ی دلخواه نباید راهی برای دیدن اطلاعات کاربران بی‌ارتباط باشد).
     */
    public static function blocked(int $uid, int $limit = 500, int $offset = 0): array
    {
        $rows = DB::all('SELECT u.id AS user_id, u.first_name, u.last_name, u.mobile, u.email, u.avatar, b.created_at,
              EXISTS (SELECT 1 FROM colleagues c WHERE (c.user_id = ? AND c.colleague_user_id = u.id) OR (c.user_id = u.id AND c.colleague_user_id = ?)) AS related
            FROM colleague_blocks b INNER JOIN users u ON u.id = b.blocked_user_id WHERE b.blocker_user_id = ? ORDER BY b.created_at DESC LIMIT 1000', [$uid, $uid, $uid]);
        $ok = self::approvedIds($uid);
        $hidden = static fn(string $key, string $at): array => ['user_id' => null, 'block_key' => $key, 'first_name' => 'کاربر', 'last_name' => 'ناشناس', 'mobile' => null, 'email' => null, 'avatar' => null, 'created_at' => $at, 'hidden' => true];
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['related'] === 0) {
                // غیرهمکار: نه اطلاعات و نه شناسه‌ی واقعی؛ فقط شناسه‌ی مبهم برای رفع مسدودی (مثل ردیف‌های «شبح»)
                $out[] = $hidden(self::blockKey($uid, (int) $r['user_id']), (string) $r['created_at']);
                continue;
            }
            unset($r['related']);
            $r['hidden'] = false;
            $r['block_key'] = null;
            if (!isset($ok[(int) $r['user_id']])) {
                $r['mobile'] = mask_mobile((string) $r['mobile']);
                $r['email'] = null;
            }
            $out[] = $r;
        }
        foreach (DB::all('SELECT ghost_key, created_at FROM colleague_block_ghosts WHERE blocker_user_id = ? LIMIT 1000', [$uid]) as $g) {
            $out[] = $hidden((string) $g['ghost_key'], (string) $g['created_at']);
        }
        usort($out, static fn($x, $y) => strcmp((string) $y['created_at'], (string) $x['created_at']));
        return array_slice($out, $offset, $limit);
    }

    /** تعداد مسدودها (همراه ردیف‌های شبح، تا شمارش هم وجود حساب را لو ندهد) */
    public static function blockedCount(int $uid): int
    {
        return (int) DB::value('SELECT (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ?) + (SELECT COUNT(*) FROM colleague_block_ghosts WHERE blocker_user_id = ?)', [$uid, $uid]);
    }

    /** شناسه‌ی مبهم یک مسدودی (برای ردیف‌های پنهان)؛ برای شناسه‌ی واقعی و ناموجود به یک شکل ساخته می‌شود */
    public static function blockKey(int $uid, int $target): string
    {
        return substr(hash_hmac('sha256', $uid . '|' . $target, (string) env('APP_KEY', 'taskam') . '|block'), 0, 24);
    }

    /** رفع مسدودی با شناسه‌ی مبهم (ردیف پنهان یا شبح) */
    public static function unblockByKey(int $uid, string $key): bool
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $key)) {
            return false;
        }
        if (DB::run('DELETE FROM colleague_block_ghosts WHERE blocker_user_id = ? AND ghost_key = ?', [$uid, $key]) > 0) {
            return true;
        }
        foreach (DB::column('SELECT blocked_user_id FROM colleague_blocks WHERE blocker_user_id = ?', [$uid]) as $t) {
            if (hash_equals(self::blockKey($uid, (int) $t), $key)) {
                return DB::run('DELETE FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$uid, (int) $t]) > 0;
            }
        }
        return false;
    }

    /**
     * مسدود کردن یک کاربر (هر کاربری؛ پاسخ برای شناسه‌ی نامعتبر و معتبر یکسان است).
     * همکاری باقی می‌ماند (تا اطلاعاتش در فهرست مسدودها دیده شود) ولی با مسدودی در هر جهت، واگذاری، دعوت به پروژه،
     * گفتگو و اعلان بین دو طرف بسته است؛ درخواست‌های همکاری در انتظار دو طرف حذف می‌شوند.
     * @return array{0:bool,1:string,2:string} [ok, پیام، کد خطا]
     */
    public static function block(int $uid, int $target): array
    {
        if (!\App\Core\Throttle::attempt('colleague_block', (string) $uid, self::BLOCK_PER_HOUR, 3600)) {
            return [false, 'تعداد مسدودسازی‌ها در یک ساعت بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.', 'RATE_LIMITED'];
        }
        if ($target <= 0 || $target === $uid) {
            return [false, 'کاربر نامعتبر است.', 'INVALID_USER_ID'];
        }
        if (!DB::value('SELECT 1 FROM users WHERE id = ? AND is_bot = 0', [$target])) {
            // شناسه‌ی ناموجود: ردیف «شبح» با همان شکل ردیف پنهان، تا پاسخ، فهرست و شمارش مسدودها وجود حساب را لو ندهند
            DB::run('INSERT IGNORE INTO colleague_block_ghosts (blocker_user_id, ghost_key) VALUES (?, ?)', [$uid, self::blockKey($uid, $target)]);
            return [true, 'کاربر مسدود شد.', ''];
        }
        DB::transaction(static function () use ($uid, $target): void {
            DB::run('INSERT IGNORE INTO colleague_blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)', [$uid, $target]);
            DB::run("DELETE FROM colleague_requests WHERE status = 'pending' AND ((requester_id = ? AND target_id = ?) OR (requester_id = ? AND target_id = ?))", [$uid, $target, $target, $uid]);
        });
        return [true, 'کاربر مسدود شد.', ''];
    }

    /** سقف مسدودسازی در ساعت برای هر کاربر */
    public const BLOCK_PER_HOUR = 20;

    /** جستجو با موبایل نرمال‌شده؛ null اگر یافت نشد. */
    public static function search(int $uid, string $mobile): ?array
    {
        $u = DB::one("SELECT id, first_name, last_name, mobile, email, avatar, allow_colleague_requests FROM users WHERE mobile = ? AND id <> ? AND status = 'active' AND is_bot = 0", [$mobile, $uid]);
        if ($u === null) {
            return null;
        }
        $id = (int) $u['id'];
        // کاربری که «دریافت درخواست همکاری» را خاموش کرده در جستجو پیدا نمی‌شود (نام و عکسش دیده نشود)، مگر همکار تأییدشده باشد
        if ((int) $u['allow_colleague_requests'] !== 1 && !Access::isColleague($uid, $id)) {
            return null;
        }
        $s = DB::one('SELECT (SELECT COUNT(*) FROM colleagues WHERE user_id = ? AND colleague_user_id = ?) AS already_added,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?) AS has_blocked_me,
              (SELECT COUNT(*) FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = \'pending\') AS has_pending_request', [$uid, $id, $uid, $id, $id, $uid, $uid, $id]) ?? [];
        return ['user' => $u, 'already_added' => (int) ($s['already_added'] ?? 0) > 0, 'is_blocked_by_me' => (int) ($s['is_blocked_by_me'] ?? 0) > 0, 'has_blocked_me' => (int) ($s['has_blocked_me'] ?? 0) > 0, 'has_pending_request' => (int) ($s['has_pending_request'] ?? 0) > 0, 'accepts_requests' => (int) $u['allow_colleague_requests'] === 1];
    }

    /**
     * افزودن همکار همیشه درخواستی است و فقط با تأیید طرف مقابل انجام می‌شود
     * (مگر او قبلاً به همین کاربر درخواست داده باشد که در این صورت همان پذیرفته می‌شود).
     * @return array{0:bool,1:string,2:string} [ok, message, state(added|pending)]
     */
    public static function add(int $uid, int $targetId): array
    {
        if ($targetId <= 0 || $targetId === $uid) {
            return [false, 'شناسه‌ی همکار نامعتبر است.', ''];
        }
        if (!\App\Core\Throttle::attempt('colleague_add', (string) $uid, 20, 3600)) {
            return [false, 'تعداد درخواست‌های همکاری زیاد است؛ یک ساعت بعد دوباره تلاش کنید.', ''];
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$uid, $targetId])) {
            return [false, 'شما این کاربر را مسدود کرده‌اید؛ ابتدا از مسدودها خارجش کنید.', ''];
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$targetId, $uid])) {
            return [false, 'این کاربر شما را مسدود کرده است.', ''];
        }
        $t = DB::one("SELECT id, allow_colleague_requests FROM users WHERE id = ? AND status = 'active' AND is_bot = 0", [$targetId]);
        if ($t === null) {
            return [false, 'کاربر مورد نظر معتبر نیست.', ''];
        }
        if (DB::value('SELECT 1 FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$uid, $targetId])) {
            return [false, 'این کاربر قبلاً در لیست همکاران شماست.', ''];
        }
        // طرف مقابل خودش قبلاً من را همکار کرده (همکاری تأییدشده): رضایتش معلوم است، مستقیم اضافه می‌شود
        if (DB::value('SELECT 1 FROM colleagues c WHERE c.user_id = ? AND c.colleague_user_id = ? AND ' . Access::approvedSql('c'), [$targetId, $uid])) {
            DB::run("INSERT IGNORE INTO colleagues (pid, user_id, colleague_user_id, permissions) VALUES (?, ?, ?, '[]')", [\App\Core\Pid::new(), $uid, $targetId]);
            DB::run("DELETE FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = 'pending'", [$uid, $targetId]);
            return [true, 'همکار به لیست شما اضافه شد.', 'added'];
        }
        if ((int) $t['allow_colleague_requests'] !== 1) {
            return [false, 'این کاربر دریافت درخواست همکاری را غیرفعال کرده است.', ''];
        }
        if (DB::value('SELECT 1 FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$uid, $targetId])) {
            return [false, 'این کاربر قبلاً در لیست همکاران شماست.', ''];
        }
        // طرف مقابل قبلاً درخواست داده: رضایت دوطرفه وجود دارد
        $theirs = (int) DB::value("SELECT id FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$targetId, $uid]);
        if ($theirs > 0) {
            self::respond($theirs, $uid, true);
            DB::run("INSERT IGNORE INTO colleagues (pid, user_id, colleague_user_id, permissions) VALUES (?, ?, ?, '[]')", [\App\Core\Pid::new(), $uid, $targetId]);
            DB::run("DELETE FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = 'pending'", [$uid, $targetId]);
            return [true, 'همکار به لیست شما اضافه شد.', 'added'];
        }
        DB::run('DELETE FROM colleague_requests WHERE requester_id = ? AND target_id = ?', [$uid, $targetId]);
        DB::insert('colleague_requests', ['requester_id' => $uid, 'target_id' => $targetId, 'status' => 'pending']);
        return [true, 'درخواست همکاری ارسال شد و پس از تأیید طرف مقابل، به لیست همکاران شما اضافه می‌شود.', 'pending'];
    }

    public static function respond(int $requestId, int $uid, bool $accept): array
    {
        $r = DB::one("SELECT id, requester_id, target_id FROM colleague_requests WHERE id = ? AND target_id = ? AND status = 'pending'", [$requestId, $uid]);
        if ($r === null) {
            return [false, 'درخواست یافت نشد یا قبلاً بررسی شده است.'];
        }
        if (!$accept) {
            DB::run("UPDATE colleague_requests SET status = 'rejected', resolved_at = NOW() WHERE id = ?", [$requestId]);
            return [true, 'درخواست همکاری رد شد.'];
        }
        $active = (int) DB::value("SELECT COUNT(*) FROM users WHERE id IN (?, ?) AND status = 'active'", [(int) $r['requester_id'], (int) $r['target_id']]);
        if ($active < 2) {
            return [false, 'یکی از کاربران معتبر نیست.'];
        }
        DB::transaction(static function () use ($r, $requestId): void {
            DB::run("INSERT IGNORE INTO colleagues (pid, user_id, colleague_user_id, permissions) VALUES (?, ?, ?, '[]')", [\App\Core\Pid::new(), (int) $r['requester_id'], (int) $r['target_id']]);
            DB::run("UPDATE colleague_requests SET status = 'accepted', resolved_at = NOW() WHERE id = ?", [$requestId]);
        });
        return [true, 'درخواست همکاری تأیید شد.'];
    }
}
