<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\DB;
use App\Core\Throttle;
use App\Core\Validate;
use App\Services\ColleagueService;

final class ColleagueApi extends ApiController
{
    private static function person(array $r): array
    {
        return ['user_id' => (int) $r['user_id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'full_name' => full_name($r), 'mobile' => $r['mobile'], 'email' => $r['email'] ?? null, 'avatar_url' => avatar_url($r['avatar'] ?? null)];
    }

    /** یافتن ردیف همکار با row_id یا user_id (فقط از لیست خود کاربر). */
    private function row(): array
    {
        $q = $this->request;
        $rowId = (int) ($q->query['row_id'] ?? $q->input('row_id', 0));
        $uidParam = (int) ($q->query['user_id'] ?? $q->input('user_id', 0));
        if ($rowId <= 0 && $uidParam <= 0) {
            $this->err('پارامتر row_id یا user_id الزامی است.', 400, 'INVALID_ID');
        }
        $row = $rowId > 0
            ? DB::one('SELECT id, colleague_user_id FROM colleagues WHERE id = ? AND user_id = ?', [$rowId, $this->uid])
            : DB::one('SELECT id, colleague_user_id FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$this->uid, $uidParam]);
        if ($row === null) {
            $this->err('همکار یافت نشد.', 404, 'COLLEAGUE_NOT_FOUND');
        }
        return $row;
    }

    public function list()
    {
        $q = $this->request->query;
        [$limit, $offset] = $this->paging();
        $only = !empty($q['only_active']);
        $out = [];
        foreach (ColleagueService::listFor($this->uid, trim((string) ($q['q'] ?? '')), $limit, $offset) as $c) {
            if ($only && ($c['is_blocked_by_me'] || $c['has_blocked_me'])) {
                continue;
            }
            $out[] = ['row_id' => (int) $c['row_id']] + self::person($c) + ['added_at' => $c['added_at'], 'is_blocked_by_me' => $c['is_blocked_by_me'], 'has_blocked_me' => $c['has_blocked_me']];
        }
        $this->ok(['count' => count($out), 'limit' => $limit, 'offset' => $offset, 'query' => trim((string) ($q['q'] ?? '')), 'colleagues' => $out]);
    }

    public function followers()
    {
        [$limit, $offset] = $this->paging();
        $rows = DB::all("SELECT c.id AS row_id, c.created_at AS added_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.email, u.avatar
            FROM colleagues c INNER JOIN users u ON u.id = c.user_id WHERE c.colleague_user_id = ? AND u.status = 'active' ORDER BY c.created_at DESC LIMIT ? OFFSET ?", [$this->uid, $limit, $offset]);
        $out = array_map(static fn($r) => ['row_id' => (int) $r['row_id']] + self::person($r) + ['added_at' => $r['added_at']], $rows);
        $this->ok(['count' => count($out), 'limit' => $limit, 'offset' => $offset, 'followers' => $out]);
    }

    public function blocked()
    {
        [$limit, $offset] = $this->paging();
        $rows = DB::all('SELECT u.id AS user_id, u.first_name, u.last_name, u.mobile, u.email, u.avatar, b.created_at AS blocked_at FROM colleague_blocks b INNER JOIN users u ON u.id = b.blocked_user_id WHERE b.blocker_user_id = ? ORDER BY b.created_at DESC LIMIT ? OFFSET ?', [$this->uid, $limit, $offset]);
        $out = array_map(static fn($r) => self::person($r) + ['blocked_at' => $r['blocked_at']], $rows);
        $this->ok(['count' => count($out), 'limit' => $limit, 'offset' => $offset, 'blocked' => $out]);
    }

    public function show()
    {
        $r = $this->row();
        $c = DB::one('SELECT c.id AS row_id, c.created_at AS added_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.email, u.avatar,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = u.id) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = u.id AND blocked_user_id = ?) AS has_blocked_me
            FROM colleagues c INNER JOIN users u ON u.id = c.colleague_user_id WHERE c.id = ?', [$this->uid, $this->uid, (int) $r['id']]);
        if (!isset(ColleagueService::approvedIds($this->uid)[(int) $c['user_id']])) {
            $c['mobile'] = mask_mobile($c['mobile']);       // همکاری یک‌طرفه‌ی قدیمی
            $c['email'] = null;
        }
        $this->ok(['row_id' => (int) $c['row_id']] + self::person($c) + ['added_at' => $c['added_at'], 'is_blocked_by_me' => (int) $c['is_blocked_by_me'] > 0, 'has_blocked_me' => (int) $c['has_blocked_me'] > 0]);
    }

    public function stats()
    {
        $u = $this->uid;
        $r = DB::one('SELECT (SELECT COUNT(*) FROM colleagues WHERE user_id = ?) colleagues_count, (SELECT COUNT(*) FROM colleagues WHERE colleague_user_id = ?) followers_count,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ?) blocked_count, (SELECT COUNT(*) FROM colleague_blocks WHERE blocked_user_id = ?) blocked_by_count', [$u, $u, $u, $u]) ?? [];
        $this->ok(['colleagues' => (int) ($r['colleagues_count'] ?? 0), 'followers' => (int) ($r['followers_count'] ?? 0), 'blocked' => (int) ($r['blocked_count'] ?? 0), 'blocked_by' => (int) ($r['blocked_by_count'] ?? 0)]);
    }

    public function search()
    {
        if (!Throttle::attempt('user_lookup', (string) $this->uid, 30, 600)) {
            header('Retry-After: 600');
            $this->err('تعداد جستجوها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 429, 'RATE_LIMITED');
        }
        $raw = trim((string) ($this->request->query['mobile'] ?? ''));
        if ($raw === '') {
            $this->err('پارامتر mobile الزامی است.', 400, 'MOBILE_REQUIRED');
        }
        $m = Validate::mobile($raw);
        if ($m === null) {
            $this->err('فرمت شماره موبایل نامعتبر است.', 400, 'MOBILE_INVALID');
        }
        $r = ColleagueService::search($this->uid, $m);
        if ($r === null) {
            $this->err('کاربری با این شماره موبایل یافت نشد.', 404, 'USER_NOT_FOUND');
        }
        $u = $r['user'];
        $this->ok(['user' => ['id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'full_name' => full_name($u), 'mobile' => $u['mobile'], 'email' => $r['already_added'] && isset(ColleagueService::approvedIds($this->uid)[(int) $u['id']]) ? $u['email'] : null, 'avatar_url' => avatar_url($u['avatar'])],
            'already_added' => $r['already_added'], 'is_blocked' => !$r['accepts_requests'], 'is_blocked_by_me' => $r['is_blocked_by_me'], 'has_blocked_me' => $r['has_blocked_me']]);
    }

    public function create()
    {
        $in = $this->body();
        $target = (int) ($in['user_id'] ?? 0);
        if ($target <= 0) {
            $m = Validate::mobile((string) ($in['mobile'] ?? ''));
            if ($m === null) {
                $this->err('mobile یا user_id معتبر الزامی است.', 400, 'MOBILE_REQUIRED');
            }
            $target = (int) DB::value("SELECT id FROM users WHERE mobile = ? AND status = 'active' AND is_bot = 0", [$m]);
            if ($target <= 0) {
                $this->err('کاربری با این شماره موبایل یافت نشد.', 404, 'USER_NOT_FOUND');
            }
        }
        [$ok, $msg, $state] = ColleagueService::add($this->uid, $target);
        if (!$ok) {
            $this->err($msg, 400, 'COLLEAGUE_ADD_FAILED');
        }
        $row = DB::one('SELECT id, created_at FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$this->uid, $target]);
        $this->ok(['row_id' => $row ? (int) $row['id'] : null, 'user_id' => $target, 'created_at' => $row['created_at'] ?? null, 'state' => $state, 'requires_approval' => $state === 'pending'], $msg);
    }

    public function delete()
    {
        $r = $this->row();
        DB::run('DELETE FROM colleagues WHERE id = ? AND user_id = ?', [(int) $r['id'], $this->uid]);
        $this->ok(['deleted' => true], 'همکار از لیست حذف شد.');
    }

    /** منسوخ از v3.0: دسترسی تسک‌ها به ازای هر پروژه با /projects/task-permissions تعیین می‌شود. */
    public function permissions()
    {
        $this->err('دسترسی همکار حذف شده است؛ دسترسی تسک‌ها را برای هر پروژه با POST /projects/task-permissions تعیین کنید.', 410, 'ENDPOINT_REMOVED');
    }

    public function block()
    {
        $t = (int) $this->request->input('user_id', 0);
        if ($t <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        if ($t === $this->uid) {
            $this->err('نمی‌توانید خودتان را مسدود کنید.', 400, 'SELF_BLOCK');
        }
        if (!DB::value('SELECT 1 FROM users WHERE id = ? AND is_bot = 0', [$t])) {
            $this->err('کاربر یافت نشد.', 404, 'USER_NOT_FOUND');
        }
        DB::run('INSERT IGNORE INTO colleague_blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)', [$this->uid, $t]);
        $this->ok(['blocked_user_id' => $t, 'blocked_at' => date('Y-m-d H:i:s')], 'کاربر مسدود شد.');
    }

    public function unblock()
    {
        $t = (int) $this->request->input('user_id', 0);
        if ($t <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        DB::run('DELETE FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$this->uid, $t]);
        $this->ok(['unblocked_user_id' => $t], 'مسدودیت کاربر برداشته شد.');
    }
}
