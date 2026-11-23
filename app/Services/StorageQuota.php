<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorText;
use App\Core\Uploader;

/** مالکیت و حجم واقعی فایل‌های ذخیره‌شده؛ سقف مستقل از سهمیه‌ی روزانه‌ی آپلود است. */
final class StorageQuota
{
    public const MB = 1048576;
    public const MAX_MB = 4294967295;
    public const FULL = 'فضای ذخیره‌سازی شما برای این فایل کافی نیست؛ فایل‌های غیرضروری را حذف کنید یا برای افزایش سقف با مدیر سیستم هماهنگ کنید.';
    public const BUSY = 'ثبت فضای ذخیره‌سازی موقتاً ممکن نیست؛ دوباره تلاش کنید.';
    private const KEY = 'storage.quota.default';

    public static function defaultLimit(): ?int
    {
        $v = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::KEY]);
        return $v === null || $v === 'null' ? null : max(0, (int) $v);
    }

    public static function setDefault(?int $mb, int $by): void
    {
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)', [self::KEY, json_encode($mb), $by]);
    }

    public static function setUser(int $uid, ?int $mb, bool $custom): void
    {
        DB::update('users', ['storage_limit_mb' => $mb, 'storage_custom' => (int) $custom], 'id = ?', [$uid]);
    }

    public static function status(int $uid): array
    {
        $u = DB::one('SELECT storage_limit_mb, storage_custom FROM users WHERE id = ?', [$uid]);
        $mb = !empty($u['storage_custom']) ? ($u['storage_limit_mb'] === null ? null : (int) $u['storage_limit_mb']) : self::defaultLimit();
        $used = self::used($uid);
        $limit = $mb === null ? null : $mb * self::MB;
        return ['used' => $used, 'limit' => $limit, 'limit_mb' => $mb, 'custom' => !empty($u['storage_custom']), 'unlimited' => $limit === null,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'percent' => $limit === null ? null : ($limit > 0 ? min(100, (int) floor($used * 100 / $limit)) : 100),
            'used_text' => human_size($used), 'limit_text' => $limit === null ? 'نامحدود' : human_size($limit)];
    }

    /** فایل حذف‌شده از دیسک سهمیه اشغال نمی‌کند؛ ردیف‌های گمشده هم پاک می‌شوند. */
    public static function used(int $uid): int
    {
        $total = 0;
        foreach (DB::all('SELECT id, directory, file_name, bytes FROM storage_files WHERE user_id = ?', [$uid]) as $f) {
            $path = Uploader::path($f['directory'], $f['file_name']);
            if ($path === null) {
                DB::run('DELETE FROM storage_files WHERE id = ?', [(int) $f['id']]);
            } else {
                clearstatcache(true, $path);
                $total += (int) filesize($path);
            }
        }
        return $total;
    }

    /** ثبت مستقل از تراکنش فرم؛ قفل هر کاربر، شمارش و ثبت را برای آپلودهای هم‌زمان سری می‌کند. */
    public static function register(int $uid, string $type, string $name): ?string
    {
        $pdo = DB::connection();
        $query = static function (string $sql, array $params = []) use ($pdo): \PDOStatement {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st;
        };
        $lock = 'kx_storage_' . substr(hash('sha256', (string) env('DB_NAME', 'taskam')), 0, 16) . '_' . $uid;
        if ((int) $query('SELECT GET_LOCK(?, 5)', [$lock])->fetchColumn() !== 1) {
            return ErrorText::apply(self::BUSY);
        }
        try {
            $path = Uploader::path($type, $name);
            if ($path === null) {
                return ErrorText::apply(self::BUSY);
            }
            clearstatcache(true, $path);
            $size = filesize($path);
            $u = $query('SELECT storage_limit_mb, storage_custom FROM users WHERE id = ?', [$uid])->fetch();
            if (!$u || $size === false || $size <= 0) {
                return ErrorText::apply(self::BUSY);
            }
            $raw = $query('SELECT body FROM content_texts WHERE `key` = ?', [self::KEY])->fetchColumn();
            $mb = $u['storage_custom'] ? $u['storage_limit_mb'] : ($raw === false || $raw === 'null' ? null : (int) $raw);
            $limit = $mb === null ? null : (int) $mb * self::MB;
            $used = 0;
            foreach ($query('SELECT directory, file_name FROM storage_files WHERE user_id = ?', [$uid])->fetchAll() as $f) {
                $p = Uploader::path($f['directory'], $f['file_name']);
                if ($p !== null) {
                    clearstatcache(true, $p);
                    $used += (int) filesize($p);
                }
            }
            if (!self::fits($used, $size, $limit)) {
                return ErrorText::apply(self::FULL);
            }
            $query('INSERT INTO storage_files (user_id, directory, file_name, bytes) VALUES (?, ?, ?, ?)', [$uid, $type, $name, $size]);
            \App\Core\AdminLog::inserted('storage_files', (int) $pdo->lastInsertId(), ['id' => (int) $pdo->lastInsertId(), 'user_id' => $uid, 'directory' => $type, 'file_name' => $name, 'bytes' => $size]);
            return null;
        } finally {
            $query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    public static function fits(int $used, int $incoming, ?int $limit): bool
    {
        return $incoming > 0 && ($limit === null || $incoming <= max(0, $limit - $used));
    }

    /** واردکردن فایل‌های قبلی فقط یک‌بار؛ تکرار اجرا نام فایل را دوباره حساب نمی‌کند. */
    public static function backfill(): void
    {
        $sources = [
            ['task_attachments', 'user_id', 'file_path', 'task_attachments'],
            ['note_attachments', 'user_id', 'file_path', 'note_attachments'],
            ['chat_messages', 'sender_id', 'attachment', 'chat_files'],
            ['support_messages', 'sender_id', 'attachment', 'support_files'],
            ['users', 'id', 'avatar', 'avatars'],
            ['projects', 'user_id', 'profile_image', 'projects'],
        ];
        foreach ($sources as [$table, $owner, $file, $dir]) {
            $after = 0;
            do {
                $rows = DB::all("SELECT id, `$owner` uid, `$file` name FROM `$table` WHERE id > ? AND `$file` IS NOT NULL ORDER BY id LIMIT 500", [$after]);
                foreach ($rows as $r) {
                    $after = (int) $r['id'];
                    $path = Uploader::path($dir, (string) $r['name']);
                    if ($path !== null && (int) $r['uid'] > 0) {
                        DB::run('INSERT IGNORE INTO storage_files (user_id, directory, file_name, bytes) VALUES (?, ?, ?, ?)', [(int) $r['uid'], $dir, $r['name'], (int) filesize($path)]);
                    }
                }
            } while (count($rows) === 500);
        }
    }
}
