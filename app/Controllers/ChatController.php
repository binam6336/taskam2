<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Jalali;
use App\Core\Throttle;
use App\Core\Uploader;
use App\Models\ChatModel;
use App\Models\UserModel;

final class ChatController extends Controller
{
    public function index()
    {
        $this->view('chat/index', ['me' => $this->uid()], ['title' => 'گفتگو', 'page' => 'chat', 'bodyClass' => 'page-chat']);
    }

    private function touch(int $uid): void
    {
        UserModel::touchSeen($uid);
    }

    private function isBot(int $id): bool
    {
        return (bool) DB::value('SELECT is_bot FROM users WHERE id = ?', [$id]);
    }

    /** ضد اسپم: حداکثر ۶۰ پیام (متن/فایل/بازارسال) در دقیقه برای هر کاربر */
    private function throttleSend(int $uid): void
    {
        if (!Throttle::attempt('chat_send', (string) $uid, 60, 60)) {
            $this->fail('ارسال پیام بیش از حد سریع است؛ چند ثانیه صبر کنید.', 429);
        }
    }

    private function assertReceiver(int $uid, int $rid): void
    {
        if ($rid <= 0) {
            $this->fail('اطلاعات ناقص');
        }
        if ($this->isBot($rid)) {
            $this->fail('این حساب ربات اطلاع‌رسانی است و امکان ارسال پیام به آن وجود ندارد');
        }
        if (!DB::value("SELECT 1 FROM users WHERE id = ? AND status = 'active'", [$rid]) || !ChatModel::canChat($uid, $rid)) {
            $this->fail('امکان ارسال پیام وجود ندارد', 403);
        }
    }

    public function time()
    {
        $this->json(['status' => 'success'] + Jalali::nowInfo());
    }

    public function send()
    {
        $uid = $this->uid();
        $rid = $this->request->int('receiver_id');
        $msg = $this->request->str('message', 2000);
        $this->assertReceiver($uid, $rid);
        $this->throttleSend($uid);
        if ($msg === '') {
            $this->fail('اطلاعات ناقص');
        }
        $this->touch($uid);
        $saved = $uid === $rid;
        $id = ChatModel::create($uid, $rid, $msg);
        $this->json(['status' => 'success', 'message' => ['id' => $id, 'sender_id' => $uid, 'receiver_id' => $rid, 'message' => $msg, 'attachment' => null, 'attachment_name' => null, 'attachment_size' => null, 'attachment_type' => null, 'is_read' => $saved ? 1 : 0, 'is_edited' => 0, 'created_at' => date('Y-m-d H:i:s'), 'ts' => time()]]);
    }

    public function sendFile()
    {
        $uid = $this->uid();
        $rid = $this->request->int('receiver_id');
        $this->assertReceiver($uid, $rid);
        $this->throttleSend($uid);
        $f = $this->request->file('attachment');
        if ($f === null) {
            $this->fail('فایلی انتخاب نشده است.');
        }
        $r = Uploader::saveAttachment($f, 'chat_files', 'chat' . $uid, Uploader::CHAT_TYPES, 50 * 1024 * 1024);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $msg = $this->request->str('message', 2000);
        $this->touch($uid);
        $saved = $uid === $rid;
        $id = ChatModel::create($uid, $rid, $msg, ['attachment' => $r['name'], 'attachment_name' => $r['original'], 'attachment_size' => $r['size'], 'attachment_type' => $r['ext']]);
        $this->json(['status' => 'success', 'message' => ['id' => $id, 'sender_id' => $uid, 'receiver_id' => $rid, 'message' => $msg, 'attachment' => $r['name'], 'attachment_name' => $r['original'], 'attachment_size' => $r['size'], 'attachment_type' => $r['ext'], 'is_read' => $saved ? 1 : 0, 'is_edited' => 0, 'created_at' => date('Y-m-d H:i:s'), 'ts' => time()]]);
    }

    public function fetch()
    {
        $uid = $this->uid();
        $r = $this->request;
        $other = (int) ($r->query['user_id'] ?? 0);
        if ($other <= 0) {
            $this->fail('شناسه نامعتبر');
        }
        if (!ChatModel::canChat($uid, $other)) {
            $this->fail('دسترسی ندارید', 403);
        }
        $this->touch($uid);
        if ($other !== $uid) {
            ChatModel::markRead($uid, $other);
        }
        $limit = max(5, min(100, (int) ($r->query['limit'] ?? 30)));
        $mode = (string) ($r->query['mode'] ?? 'initial');
        $res = ChatModel::fetch($uid, $other, $mode, (int) ($r->query['before_id'] ?? 0), (int) ($r->query['after_id'] ?? 0), (int) ($r->query['target_id'] ?? 0), $limit);
        $this->json(['status' => 'success'] + $res + ['mode' => $mode]);
    }

    public function search()
    {
        $uid = $this->uid();
        $q = mb_substr(trim((string) ($this->request->query['q'] ?? '')), 0, 100);
        if (mb_strlen($q) < 2) {
            $this->fail('حداقل ۲ کاراکتر وارد کنید');
        }
        $scope = ($this->request->query['scope'] ?? 'all') === 'conversation' ? 'conversation' : 'all';
        $other = (int) ($this->request->query['user_id'] ?? 0);
        $limit = max(10, min(200, (int) ($this->request->query['limit'] ?? 60)));
        $rows = ChatModel::search($uid, $q, $scope, $other, $limit);
        $this->json(['status' => 'success', 'query' => $q, 'scope' => $scope, 'count' => count($rows), 'results' => $rows]);
    }

    public function contacts()
    {
        $this->json(['status' => 'success', 'list' => ChatModel::contacts($this->uid())]);
    }

    public function unreadCounts()
    {
        $this->json(['status' => 'success', 'counts' => (object) ChatModel::unreadCounts($this->uid())]);
    }

    public function totalUnread()
    {
        $this->json(['status' => 'success', 'total' => ChatModel::totalUnread($this->uid())]);
    }

    public function markRead()
    {
        $other = $this->request->int('user_id');
        if ($other <= 0) {
            $this->fail('شناسه نامعتبر');
        }
        $this->json(['status' => 'success', 'affected' => ChatModel::markRead($this->uid(), $other)]);
    }

    private function ownWithinWindow(int $uid, int $msgId): array
    {
        $m = DB::one('SELECT * FROM chat_messages WHERE id = ? AND sender_id = ?', [$msgId, $uid]);
        if ($m === null) {
            $this->fail('پیام یافت نشد', 404);
        }
        $age = time() - (int) strtotime((string) $m['created_at']);
        if ($age > ChatModel::EDIT_WINDOW) {
            $this->fail('مهلت ۱ ساعته ویرایش/حذف این پیام به پایان رسیده است', 422, ['remaining' => 0]);
        }
        return $m;
    }

    public function edit()
    {
        $uid = $this->uid();
        $id = $this->request->int('message_id');
        $text = $this->request->str('message', 2000);
        if ($id <= 0) {
            $this->fail('شناسه پیام نامعتبر');
        }
        if ($text === '') {
            $this->fail('متن پیام نمی‌تواند خالی باشد');
        }
        $m = $this->ownWithinWindow($uid, $id);
        if (!empty($m['attachment'])) {
            $this->fail('پیام‌های دارای فایل قابل ویرایش نیستند');
        }
        if (!empty($m['forwarded_from'])) {
            $this->fail('پیام بازارسال‌شده قابل ویرایش نیست');
        }
        $current = (string) ChatModel::open($m['message'], (int) $m['sender_id'], (int) $m['receiver_id']);
        if (trim($current) === $text) {
            $this->json(['status' => 'success', 'message' => ['id' => $id, 'message' => $text, 'is_edited' => (int) $m['is_edited'], 'edited_at' => null, 'unchanged' => true]]);
        }
        DB::run('UPDATE chat_messages SET message = ?, is_edited = 1, edited_at = NOW() WHERE id = ? AND sender_id = ?', [(string) ChatModel::seal($text, (int) $m['sender_id'], (int) $m['receiver_id']), $id, $uid]);
        $u = DB::one('SELECT id, is_edited, edited_at FROM chat_messages WHERE id = ?', [$id]);
        $this->json(['status' => 'success', 'message' => ['id' => (int) $u['id'], 'message' => $text, 'is_edited' => (int) $u['is_edited'], 'edited_at' => $u['edited_at']]]);
    }

    public function delete()
    {
        $uid = $this->uid();
        $id = $this->request->int('message_id');
        if ($id <= 0) {
            $this->fail('شناسه پیام نامعتبر');
        }
        $m = $this->ownWithinWindow($uid, $id);
        DB::run('DELETE FROM chat_messages WHERE id = ? AND sender_id = ?', [$id, $uid]);
        Uploader::delete('chat_files', $m['attachment']);
        $this->json(['status' => 'success', 'message' => 'پیام برای هر دو طرف حذف شد', 'deleted_id' => $id]);
    }

    /** بازارسال یک پیام (متن و فایل) به گفتگوی دیگر؛ فایل کپی می‌شود تا حذف پیام اصلی روی آن اثر نگذارد. */
    public function forward()
    {
        $uid = $this->uid();
        $id = $this->request->int('message_id');
        $rid = $this->request->int('receiver_id');
        $m = DB::one('SELECT * FROM chat_messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)', [$id, $uid, $uid]);
        if ($m === null) {
            $this->fail('پیام یافت نشد', 404);
        }
        $other = (int) $m['sender_id'] === $uid ? (int) $m['receiver_id'] : (int) $m['sender_id'];
        if ($id <= ChatModel::clearedUpTo($uid, $other)) {
            $this->fail('پیام یافت نشد', 404);
        }
        $this->assertReceiver($uid, $rid);
        $this->throttleSend($uid);
        $m = ChatModel::normalize($m);
        $file = ['forwarded_from' => $m['forwarded_from'] ?: $m['sender_id']];
        if (!empty($m['attachment'])) {
            $src = Uploader::path('chat_files', (string) $m['attachment']);
            if ($src === null) {
                $this->fail('فایل این پیام دیگر موجود نیست');
            }
            $name = 'chat' . $uid . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . strtolower((string) $m['attachment_type']);
            if (!@copy($src, Uploader::dir('chat_files') . '/' . $name)) {
                $this->fail('کپی فایل برای بازارسال ممکن نشد');
            }
            $file += ['attachment' => $name, 'attachment_name' => $m['attachment_name'], 'attachment_size' => $m['attachment_size'], 'attachment_type' => $m['attachment_type']];
        }
        $this->touch($uid);
        $text = (string) $m['message'];
        $newId = ChatModel::create($uid, $rid, $text, $file);
        $row = DB::one('SELECT * FROM chat_messages WHERE id = ?', [$newId]);
        $this->json(['status' => 'success', 'message' => ChatModel::normalize($row), 'info' => 'پیام بازارسال شد']);
    }

    /** «حذف تاریخچه»: گفتگو فقط برای همین کاربر خالی می‌شود؛ طرف مقابل پیام‌ها را می‌بیند. */
    public function clearHistory()
    {
        $uid = $this->uid();
        $other = $this->request->int('user_id');
        if ($other <= 0 || !ChatModel::canChat($uid, $other) && !DB::value('SELECT 1 FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1', [$uid, $other, $other, $uid])) {
            $this->fail('گفتگو یافت نشد', 404);
        }
        ChatModel::clearHistory($uid, $other);
        $this->json(['status' => 'success', 'message' => 'تاریخچه‌ی این گفتگو برای شما پاک شد']);
    }

    public function heartbeat()
    {
        $this->touch($this->uid());
        $this->json(['status' => 'success', 'now' => time()]);
    }

    public function userStatus()
    {
        $uid = $this->uid();
        $other = (int) ($this->request->query['user_id'] ?? 0);
        if ($other <= 0) {
            $this->fail('شناسه نامعتبر');
        }
        if ($other === $uid) {
            $this->json(['status' => 'success', 'is_saved' => true, 'is_online' => true, 'last_seen' => time(), 'last_seen_text' => null, 'last_seen_absolute' => null, 'last_seen_relative' => null, 'now' => time(), 'threshold' => ChatModel::ONLINE_THRESHOLD]);
        }
        $t = DB::one("SELECT is_bot, last_seen FROM users WHERE id = ? AND status = 'active'", [$other]);
        // وضعیت آنلاین فقط برای مخاطبان (همکار یا طرف گفتگو، بدون مسدودی) — نه هر کاربری با حدس شناسه
        if ($t === null || !ChatModel::canChat($uid, $other)) {
            $this->fail('کاربر یافت نشد', 404);
        }
        if ((int) $t['is_bot'] === 1) {
            $this->json(['status' => 'success', 'is_saved' => false, 'is_bot' => true, 'is_online' => false, 'last_seen' => null, 'last_seen_text' => 'ربات اطلاع‌رسانی', 'last_seen_absolute' => null, 'last_seen_relative' => null, 'now' => time(), 'threshold' => ChatModel::ONLINE_THRESHOLD]);
        }
        $this->json(['status' => 'success', 'is_saved' => false, 'is_bot' => false, 'now' => time(), 'threshold' => ChatModel::ONLINE_THRESHOLD] + ChatModel::presence($t['last_seen'] !== null ? (int) $t['last_seen'] : null));
    }

    public function usersStatus()
    {
        $uid = $this->uid();
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($this->request->query['user_ids'] ?? ''))), static fn($i) => $i > 0 && $i !== $uid));
        $ids = array_slice($ids, 0, 200);
        $out = [];
        if ($ids) {
            $sql = "SELECT u.id, u.is_bot, u.last_seen FROM users u WHERE u.id IN (" . DB::in($ids) . ") AND u.status = 'active'
                AND NOT EXISTS (SELECT 1 FROM colleague_blocks b WHERE (b.blocker_user_id = ? AND b.blocked_user_id = u.id) OR (b.blocker_user_id = u.id AND b.blocked_user_id = ?))
                AND (EXISTS (SELECT 1 FROM colleagues c WHERE (c.user_id = ? AND c.colleague_user_id = u.id) OR (c.user_id = u.id AND c.colleague_user_id = ?))
                  OR EXISTS (SELECT 1 FROM chat_messages m WHERE (m.sender_id = ? AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = ?)))";
            foreach (DB::all($sql, array_merge($ids, [$uid, $uid, $uid, $uid, $uid, $uid])) as $r) {
                $out[(int) $r['id']] = (int) $r['is_bot'] === 1
                    ? ['is_bot' => true, 'is_online' => false, 'last_seen' => null, 'last_seen_text' => 'ربات اطلاع‌رسانی', 'last_seen_absolute' => null, 'last_seen_relative' => null]
                    : ['is_bot' => false] + ChatModel::presence($r['last_seen'] !== null ? (int) $r['last_seen'] : null);
            }
        }
        $this->json(['status' => 'success', 'users' => (object) $out, 'now' => time(), 'threshold' => ChatModel::ONLINE_THRESHOLD]);
    }

    public function savedInfo()
    {
        $uid = $this->uid();
        $this->touch($uid);
        $last = DB::one('SELECT id, sender_id, receiver_id, message, attachment, attachment_name, attachment_type, attachment_size, created_at FROM chat_messages WHERE sender_id = ? AND receiver_id = ? ORDER BY id DESC LIMIT 1', [$uid, $uid]);
        $total = (int) DB::value('SELECT COUNT(*) FROM chat_messages WHERE sender_id = ? AND receiver_id = ?', [$uid, $uid]);
        if ($last) {
            $last['id'] = (int) $last['id'];
            $last['message'] = ChatModel::open($last['message'], (int) $last['sender_id'], (int) $last['receiver_id']);
            $last['attachment_name'] = ChatModel::open($last['attachment_name'], (int) $last['sender_id'], (int) $last['receiver_id']);
            unset($last['sender_id'], $last['receiver_id']);
            $last['attachment_size'] = $last['attachment_size'] ? (int) $last['attachment_size'] : null;
        }
        $this->json(['status' => 'success', 'last_message' => $last ?: null, 'total_count' => $total]);
    }

    public function latestUnread()
    {
        $uid = $this->uid();
        $row = DB::one('SELECT cm.sender_id, cm.receiver_id, cm.id AS message_id, cm.message, cm.attachment_name, cm.created_at, u.first_name, u.last_name, u.mobile, u.is_bot
            FROM chat_messages cm INNER JOIN users u ON u.id = cm.sender_id
            WHERE cm.receiver_id = ? AND cm.is_read = 0 AND cm.sender_id <> cm.receiver_id ORDER BY cm.id DESC LIMIT 1', [$uid]);
        if ($row === null) {
            $this->json(['status' => 'success', 'latest' => null]);
        }
        $row['sender_id'] = (int) $row['sender_id'];
        $row['message_id'] = (int) $row['message_id'];
        $row['from_name'] = full_name($row);
        $row['message'] = preg_replace('/\[\[(task|call):[a-f0-9]+\]\]/i', '', (string) ChatModel::open($row['message'], $row['sender_id'], (int) $row['receiver_id']));
        $row['attachment_name'] = ChatModel::open($row['attachment_name'], $row['sender_id'], (int) $row['receiver_id']);
        unset($row['receiver_id']);
        $this->json(['status' => 'success', 'latest' => $row]);
    }
}
