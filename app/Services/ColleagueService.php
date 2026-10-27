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
        foreach ($rows as &$r) {
            $r['perms'] = Access::decode($r['permissions'], array_keys(Access::COLLEAGUE_PERMS));
            $r['is_blocked_by_me'] = (int) $r['is_blocked_by_me'] > 0;
            $r['has_blocked_me'] = (int) $r['has_blocked_me'] > 0;
        }
        return $rows;
    }

    public static function incomingRequests(int $uid): array
    {
        return DB::all("SELECT r.id, r.created_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar FROM colleague_requests r INNER JOIN users u ON u.id = r.requester_id WHERE r.target_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC", [$uid]);
    }

    public static function outgoingRequests(int $uid): array
    {
        return DB::all("SELECT r.id, r.created_at, u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar FROM colleague_requests r INNER JOIN users u ON u.id = r.target_id WHERE r.requester_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC", [$uid]);
    }

    public static function blocked(int $uid): array
    {
        return DB::all('SELECT u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar, b.created_at FROM colleague_blocks b INNER JOIN users u ON u.id = b.blocked_user_id WHERE b.blocker_user_id = ? ORDER BY b.created_at DESC', [$uid]);
    }

    /** جستجو با موبایل نرمال‌شده؛ null اگر یافت نشد. */
    public static function search(int $uid, string $mobile): ?array
    {
        $u = DB::one("SELECT id, first_name, last_name, mobile, email, avatar, allow_colleague_requests FROM users WHERE mobile = ? AND id <> ? AND status = 'active' AND is_bot = 0", [$mobile, $uid]);
        if ($u === null) {
            return null;
        }
        $id = (int) $u['id'];
        $s = DB::one('SELECT (SELECT COUNT(*) FROM colleagues WHERE user_id = ? AND colleague_user_id = ?) AS already_added,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?) AS has_blocked_me,
              (SELECT COUNT(*) FROM colleague_requests WHERE requester_id = ? AND target_id = ? AND status = \'pending\') AS has_pending_request', [$uid, $id, $uid, $id, $id, $uid, $uid, $id]) ?? [];
        return ['user' => $u, 'already_added' => (int) ($s['already_added'] ?? 0) > 0, 'is_blocked_by_me' => (int) ($s['is_blocked_by_me'] ?? 0) > 0, 'has_blocked_me' => (int) ($s['has_blocked_me'] ?? 0) > 0, 'has_pending_request' => (int) ($s['has_pending_request'] ?? 0) > 0, 'accepts_requests' => (int) $u['allow_colleague_requests'] === 1];
    }

    /** @return array{0:bool,1:string,2:string} [ok, message, state(added|pending)] */
    public static function add(int $uid, int $targetId): array
    {
        if ($targetId <= 0 || $targetId === $uid) {
            return [false, 'شناسه‌ی همکار نامعتبر است.', ''];
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$uid, $targetId])) {
            return [false, 'شما این کاربر را مسدود کرده‌اید؛ ابتدا از مسدودها خارجش کنید.', ''];
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$targetId, $uid])) {
            return [false, 'این کاربر شما را مسدود کرده است.', ''];
        }
        $t = DB::one("SELECT id, first_name, last_name, mobile, allow_colleague_requests, project_join_requires_approval FROM users WHERE id = ? AND status = 'active' AND is_bot = 0", [$targetId]);
        if ($t === null) {
            return [false, 'کاربر مورد نظر معتبر نیست.', ''];
        }
        if ((int) $t['allow_colleague_requests'] !== 1) {
            return [false, 'این کاربر دریافت درخواست همکاری را غیرفعال کرده است.', ''];
        }
        if (DB::value('SELECT 1 FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$uid, $targetId])) {
            return [false, 'این کاربر قبلاً در لیست همکاران شماست.', ''];
        }
        if ((int) $t['project_join_requires_approval'] === 1) {
            DB::run('DELETE FROM colleague_requests WHERE requester_id = ? AND target_id = ?', [$uid, $targetId]);
            DB::insert('colleague_requests', ['requester_id' => $uid, 'target_id' => $targetId, 'status' => 'pending']);
            return [true, 'درخواست همکاری برای ' . full_name($t) . ' ارسال شد و در انتظار تأیید ایشان است.', 'pending'];
        }
        DB::run("INSERT IGNORE INTO colleagues (user_id, colleague_user_id, permissions) VALUES (?, ?, '[]')", [$uid, $targetId]);
        return [true, 'همکار به لیست شما اضافه شد.', 'added'];
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
            DB::run("INSERT IGNORE INTO colleagues (user_id, colleague_user_id, permissions) VALUES (?, ?, '[]')", [(int) $r['requester_id'], (int) $r['target_id']]);
            DB::run("UPDATE colleague_requests SET status = 'accepted', resolved_at = NOW() WHERE id = ?", [$requestId]);
        });
        return [true, 'درخواست همکاری تأیید شد.'];
    }
}
