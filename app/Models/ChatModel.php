<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\DB;

final class ChatModel
{
    public const ONLINE_THRESHOLD = 25;
    public const EDIT_WINDOW = 3600;
    private const COLS = 'id, sender_id, receiver_id, message, attachment, attachment_name, attachment_size, attachment_type, is_read, is_edited, edited_at, created_at';

    public static function canChat(int $a, int $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE (blocker_user_id = ? AND blocked_user_id = ?) OR (blocker_user_id = ? AND blocked_user_id = ?) LIMIT 1', [$a, $b, $b, $a])) {
            return false;
        }
        if (DB::value('SELECT 1 FROM colleagues WHERE (user_id = ? AND colleague_user_id = ?) OR (user_id = ? AND colleague_user_id = ?) LIMIT 1', [$a, $b, $b, $a])) {
            return true;
        }
        return (bool) DB::value('SELECT 1 FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1', [$a, $b, $b, $a]);
    }

    // ───── رمزگذاری پیام‌ها (متن پیام + نام فایل) ─────

    private const LOCKED = '🔒 پیام رمزگذاری‌شده — با رمز فعلی (ENCODE_PASSWORD) قابل خواندن نیست';
    private const SEARCH_SCAN = 4000;

    /** زمینه‌ی AAD: ciphertext به فرستنده/گیرنده‌ی همین پیام بسته می‌شود. */
    public static function aad(int $sender, int $receiver): string
    {
        return 'chat:' . $sender . ':' . $receiver;
    }

    /** متن را برای ذخیره در دیتابیس انکد می‌کند (رشته‌ی خالی/NULL دست‌نخورده می‌ماند). */
    public static function seal(?string $text, int $sender, int $receiver): ?string
    {
        if ($text === null || $text === '' || !Crypto::configured()) {
            return $text;
        }
        return Crypto::encode($text, null, self::aad($sender, $receiver));
    }

    /** مقدار ذخیره‌شده را دیکد می‌کند؛ داده‌ی قدیمیِ متن‌ساده همان را برمی‌گرداند، انکدِ غیرقابل‌دیکد → پیام قفل. */
    public static function open(?string $stored, int $sender, int $receiver): ?string
    {
        if ($stored === null || $stored === '' || !Crypto::isEncoded($stored)) {
            return $stored;
        }
        $plain = Crypto::decode($stored, null, self::aad($sender, $receiver));
        return $plain ?? self::LOCKED;
    }

    /**
     * درج پیام (تنها مسیر نوشتن در chat_messages): متن و نام فایل به‌صورت رمزشده ذخیره می‌شوند.
     * @param array $file ['attachment','attachment_name','attachment_size','attachment_type']
     */
    public static function create(int $sender, int $receiver, string $message, array $file = []): int
    {
        $saved = $sender === $receiver;
        $row = [
            'sender_id' => $sender, 'receiver_id' => $receiver,
            'message' => (string) self::seal($message, $sender, $receiver),
            'is_read' => $saved ? 1 : 0, 'read_at' => $saved ? date('Y-m-d H:i:s') : null,
        ];
        if ($file) {
            $row['attachment'] = $file['attachment'];
            $row['attachment_name'] = self::seal((string) $file['attachment_name'], $sender, $receiver);
            $row['attachment_size'] = $file['attachment_size'];
            $row['attachment_type'] = $file['attachment_type'];
        }
        return DB::insert('chat_messages', $row);
    }

    public static function normalize(array $m): array
    {
        $m['message'] = self::open($m['message'] ?? null, (int) $m['sender_id'], (int) $m['receiver_id']);
        if (array_key_exists('attachment_name', $m)) {
            $m['attachment_name'] = self::open($m['attachment_name'], (int) $m['sender_id'], (int) $m['receiver_id']);
        }
        $m['id'] = (int) $m['id'];
        $m['sender_id'] = (int) $m['sender_id'];
        $m['receiver_id'] = (int) $m['receiver_id'];
        $m['is_read'] = (int) $m['is_read'];
        $m['is_edited'] = (int) ($m['is_edited'] ?? 0);
        $m['attachment_size'] = !empty($m['attachment_size']) ? (int) $m['attachment_size'] : null;
        return $m;
    }

    /** @return array{messages:array,has_more_before:bool,has_more_after:bool} */
    public static function fetch(int $me, int $other, string $mode, int $beforeId, int $afterId, int $targetId, int $limit): array
    {
        $pair = '((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))';
        $pp = [$me, $other, $other, $me];
        $sel = 'SELECT ' . self::COLS . ' FROM chat_messages WHERE ' . $pair;
        $before = false;
        $after = false;
        if ($mode === 'older' && $beforeId > 0) {
            $rows = DB::all($sel . ' AND id < ? ORDER BY id DESC LIMIT ?', array_merge($pp, [$beforeId, $limit + 1]));
            if (count($rows) > $limit) {
                $before = true;
                array_pop($rows);
            }
            $msgs = array_reverse($rows);
            $after = true;
        } elseif ($mode === 'newer' && $afterId > 0) {
            $msgs = DB::all($sel . ' AND id > ? ORDER BY id ASC LIMIT ?', array_merge($pp, [$afterId, $limit]));
            $before = true;
            if (count($msgs) === $limit) {
                $last = (int) end($msgs)['id'];
                $after = (bool) DB::value('SELECT 1 FROM chat_messages WHERE ' . $pair . ' AND id > ? LIMIT 1', array_merge($pp, [$last]));
            }
        } elseif ($mode === 'around' && $targetId > 0) {
            $half = intdiv($limit, 2);
            $b = array_reverse(DB::all($sel . ' AND id < ? ORDER BY id DESC LIMIT ?', array_merge($pp, [$targetId, $half])));
            $t = DB::one($sel . ' AND id = ? LIMIT 1', array_merge($pp, [$targetId]));
            $a = DB::all($sel . ' AND id > ? ORDER BY id ASC LIMIT ?', array_merge($pp, [$targetId, $half]));
            $msgs = array_merge($b, $t ? [$t] : [], $a);
            $before = count($b) >= $half;
            $after = count($a) >= $half;
        } else {
            $rows = DB::all($sel . ' ORDER BY id DESC LIMIT ?', array_merge($pp, [$limit + 1]));
            if (count($rows) > $limit) {
                $before = true;
                array_pop($rows);
            }
            $msgs = array_reverse($rows);
        }
        return ['messages' => array_map([self::class, 'normalize'], $msgs), 'has_more_before' => $before, 'has_more_after' => $after];
    }

    public static function contacts(int $me): array
    {
        $rows = DB::all("SELECT u.id AS user_id, u.first_name, u.last_name, u.mobile, u.avatar, u.is_bot,
              (SELECT COUNT(*) FROM colleague_blocks cb WHERE cb.blocker_user_id = ? AND cb.blocked_user_id = u.id) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM colleague_blocks cb WHERE cb.blocker_user_id = u.id AND cb.blocked_user_id = ?) AS has_blocked_me,
              (SELECT COUNT(*) FROM chat_messages cm WHERE cm.sender_id = u.id AND cm.receiver_id = ? AND cm.is_read = 0) AS unread_count,
              (SELECT MAX(cm.created_at) FROM chat_messages cm WHERE (cm.sender_id = u.id AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = u.id)) AS last_msg_time
            FROM users u
            WHERE u.id <> ? AND u.status = 'active' AND (
                EXISTS (SELECT 1 FROM colleagues c WHERE c.user_id = ? AND c.colleague_user_id = u.id)
             OR EXISTS (SELECT 1 FROM colleagues c WHERE c.user_id = u.id AND c.colleague_user_id = ?)
             OR EXISTS (SELECT 1 FROM chat_messages cm2 WHERE (cm2.sender_id = u.id AND cm2.receiver_id = ?) OR (cm2.sender_id = ? AND cm2.receiver_id = u.id)))
            ORDER BY (last_msg_time IS NULL) ASC, last_msg_time DESC, u.first_name ASC", [$me, $me, $me, $me, $me, $me, $me, $me, $me, $me]);
        foreach ($rows as &$r) {
            $r['user_id'] = (int) $r['user_id'];
            $r['is_blocked_by_me'] = (int) $r['is_blocked_by_me'] === 1;
            $r['has_blocked_me'] = (int) $r['has_blocked_me'] === 1;
            $r['unread_count'] = (int) $r['unread_count'];
            $r['is_bot'] = (int) $r['is_bot'] === 1;
            $r['avatar_url'] = avatar_url($r['avatar']);
        }
        return $rows;
    }

    public static function unreadCounts(int $me): array
    {
        $out = [];
        foreach (DB::all('SELECT sender_id, COUNT(*) AS cnt FROM chat_messages WHERE receiver_id = ? AND is_read = 0 AND sender_id <> receiver_id GROUP BY sender_id', [$me]) as $r) {
            $out[(int) $r['sender_id']] = (int) $r['cnt'];
        }
        return $out;
    }

    public static function totalUnread(int $me): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0 AND sender_id <> receiver_id', [$me]);
    }

    public static function markRead(int $me, int $other): int
    {
        return DB::run('UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0', [$other, $me]);
    }

    public static function search(int $me, string $q, string $scope, int $other, int $limit): array
    {
        $cols = 'cm.id, cm.sender_id, cm.receiver_id, cm.message, cm.attachment, cm.attachment_name, cm.attachment_type, cm.attachment_size, cm.is_read, cm.is_edited, cm.created_at, u.first_name, u.last_name, u.mobile';
        $from = ' FROM chat_messages cm LEFT JOIN users u ON u.id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END WHERE ';
        if ($scope === 'conversation' && $other > 0) {
            $where = '((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?))';
            $wp = [$me, $other, $other, $me];
        } else {
            $where = '(cm.sender_id = ? OR cm.receiver_id = ?)';
            $wp = [$me, $me];
        }

        if (!Crypto::configured()) {
            // پیام‌ها متن‌ساده‌اند → جستجوی SQL
            $like = DB::like($q);
            $found = DB::all('SELECT ' . $cols . $from . $where . ' AND (cm.message LIKE ? OR cm.attachment_name LIKE ?) ORDER BY cm.id DESC LIMIT ?', array_merge([$me], $wp, [$like, $like, $limit]));
            $rows = array_map([self::class, 'normalize'], $found);
        } else {
            // متن در دیتابیس رمزشده است و با LIKE جستجو نمی‌شود → آخرین پیام‌های کاربر دیکد و در PHP فیلتر می‌شوند
            $all = DB::all('SELECT ' . $cols . $from . $where . ' ORDER BY cm.id DESC LIMIT ?', array_merge([$me], $wp, [self::SEARCH_SCAN]));
            $rows = [];
            foreach ($all as $r) {
                $r = self::normalize($r);
                if (mb_stripos((string) $r['message'] . ' ' . (string) $r['attachment_name'], $q, 0, 'UTF-8') !== false) {
                    $rows[] = $r;
                    if (count($rows) >= $limit) {
                        break;
                    }
                }
            }
        }
        foreach ($rows as &$r) {
            $o = $r['sender_id'] === $me ? $r['receiver_id'] : $r['sender_id'];
            $r['other_user_id'] = $o;
            $r['is_mine'] = $r['sender_id'] === $me;
            $r['from_saved'] = $r['sender_id'] === $me && $r['receiver_id'] === $me;
            $r['from_name'] = $r['from_saved'] ? 'پیام‌های ذخیره‌شده' : full_name($r);
        }
        return $rows;
    }

    public static function presence(?int $lastSeen): array
    {
        $online = $lastSeen !== null && (time() - $lastSeen) <= self::ONLINE_THRESHOLD;
        if ($lastSeen === null || $lastSeen <= 0) {
            return ['is_online' => false, 'last_seen' => null, 'last_seen_text' => 'آخرین بازدید: نامشخص', 'last_seen_absolute' => null, 'last_seen_relative' => null];
        }
        $rel = \App\Core\Jalali::ago(date('Y-m-d H:i:s', $lastSeen));
        $abs = \App\Core\Jalali::format(date('Y-m-d H:i:s', $lastSeen), true);
        return ['is_online' => $online, 'last_seen' => $lastSeen, 'last_seen_text' => 'آخرین بازدید: ' . $rel, 'last_seen_absolute' => $abs, 'last_seen_relative' => $rel];
    }
}
