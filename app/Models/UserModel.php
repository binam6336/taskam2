<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class UserModel
{
    public const PUBLIC_COLS = 'id, mobile, first_name, last_name, email, avatar, role, status, created_at';

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findByMobile(string $mobile): ?array
    {
        return DB::one('SELECT * FROM users WHERE mobile = ? AND is_bot = 0 LIMIT 1', [$mobile]);
    }

    public static function mobileExists(string $mobile, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE mobile = ? AND id <> ? LIMIT 1', [$mobile, $exceptId]);
    }

    public static function emailExists(string $email, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $exceptId]);
    }

    /** ربات اطلاع‌رسانی (در صورت نبود ساخته می‌شود). */
    public static function botId(): int
    {
        static $id = 0;
        if ($id > 0) {
            return $id;
        }
        $id = (int) DB::value('SELECT id FROM users WHERE is_bot = 1 ORDER BY id LIMIT 1');
        if ($id > 0) {
            return $id;
        }
        return $id = DB::insert('users', [
            'mobile' => '00000000000', 'first_name' => 'تسکام', 'last_name' => '',
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT),
            'role' => 'user', 'status' => 'active', 'is_bot' => 1,
        ]);
    }

    /** آمار کلی کاربران برای ادمین. */
    public static function counts(): array
    {
        $r = DB::one("SELECT COUNT(*) total,
            COALESCE(SUM(status='active'),0) active, COALESCE(SUM(status='pending'),0) pending,
            COALESCE(SUM(status='blocked'),0) blocked, COALESCE(SUM(status='deactivated'),0) deactivated,
            COALESCE(SUM(role='admin'),0) admins FROM users WHERE is_bot = 0") ?? [];
        return array_map('intval', $r + ['total' => 0]);
    }

    public static function allForAdmin(): array
    {
        return DB::all("SELECT id, mobile, first_name, last_name, email, avatar, role, status, admin_permissions, last_seen, last_login_at, created_at
                          FROM users WHERE is_bot = 0 ORDER BY (role = 'admin') DESC, created_at DESC");
    }

    public static function touchSeen(int $id): void
    {
        DB::run('UPDATE users SET last_seen = ? WHERE id = ?', [time(), $id]);
    }

    /** حذف کامل کاربر + فایل‌های فیزیکی او. */
    public static function deleteWithFiles(int $id): void
    {
        $u = self::find($id);
        if ($u === null) {
            return;
        }
        $files = [
            'avatars' => array_filter([(string) $u['avatar']]),
            'projects' => DB::column('SELECT profile_image FROM projects WHERE user_id = ? AND profile_image IS NOT NULL', [$id]),
            'task_attachments' => DB::column('SELECT file_path FROM task_attachments WHERE task_id IN (SELECT id FROM tasks WHERE user_id = ?) OR user_id = ?', [$id, $id]),
            'note_attachments' => DB::column('SELECT na.file_path FROM note_attachments na WHERE na.user_id = ? OR na.note_id IN (SELECT tn.id FROM task_notes tn WHERE tn.user_id = ? OR tn.task_id IN (SELECT id FROM tasks WHERE user_id = ?))', [$id, $id, $id]),
            'chat_files' => DB::column('SELECT attachment FROM chat_messages WHERE (sender_id = ? OR receiver_id = ?) AND attachment IS NOT NULL', [$id, $id]),
        ];
        DB::run('DELETE FROM users WHERE id = ?', [$id]);
        foreach ($files as $type => $names) {
            foreach ($names as $n) {
                \App\Core\Uploader::delete($type, (string) $n);
            }
        }
    }
}
