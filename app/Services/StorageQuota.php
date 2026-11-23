<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorText;
use App\Core\Uploader;

/** سهمیه‌ی فضای پروژه؛ هویت آپلودکننده برای سابقه حفظ می‌شود، سقف به پروژه تعلق دارد. */
final class StorageQuota
{
    public const MB = 1048576;
    public const MAX_MB = 4294967295;
    public const FULL = 'فضای ذخیره‌سازی پروژه برای این فایل کافی نیست؛ فایل‌های غیرضروری را حذف کنید یا برای افزایش سقف با مدیر سیستم هماهنگ کنید.';
    public const BUSY = 'ثبت فضای ذخیره‌سازی موقتاً ممکن نیست؛ دوباره تلاش کنید.';

    private static ?\PDO $connection = null;

    private static function connection(): \PDO
    {
        return self::$connection ??= DB::connection();
    }

    private static function query(\PDO $pdo, string $sql, array $params = []): \PDOStatement
    {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s;
    }

    private static function lockName(string $kind, int $id): string
    {
        return 'kx_storage_' . substr(hash('sha256', (string) env('DB_NAME', 'taskam')), 0, 16) . '_' . $kind . '_' . $id;
    }

    private static function lock(\PDO $pdo, string $name): void
    {
        if ((int) self::query($pdo, 'SELECT GET_LOCK(?, 5)', [$name])->fetchColumn() !== 1) {
            throw new \RuntimeException(self::BUSY);
        }
    }

    private static function unlock(\PDO $pdo, string $name): void
    {
        self::query($pdo, 'SELECT RELEASE_LOCK(?)', [$name]);
    }

    private static function sum(\PDO $pdo, int $projectId): int
    {
        $used = 0;
        foreach (self::query($pdo, 'SELECT directory, file_name FROM storage_files WHERE project_id = ?', [$projectId])->fetchAll() as $f) {
            $p = Uploader::path($f['directory'], $f['file_name']);
            if ($p !== null) {
                clearstatcache(true, $p);
                $used += (int) filesize($p);
            }
        }
        return $used;
    }

    public static function status(int $projectId): array
    {
        $mb = DB::value('SELECT storage_limit_mb FROM projects WHERE id = ?', [$projectId]);
        $mb = $mb === null ? null : (int) $mb;
        $used = self::used($projectId);
        $limit = $mb === null ? null : $mb * self::MB;
        return ['used' => $used, 'limit' => $limit, 'limit_mb' => $mb, 'unlimited' => $limit === null,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'percent' => $limit === null ? null : ($limit > 0 ? min(100, (int) floor($used * 100 / $limit)) : 100),
            'used_text' => human_size($used), 'limit_text' => $limit === null ? 'نامحدود' : human_size($limit)];
    }

    public static function used(int $projectId): int
    {
        return self::sum(DB::pdo(), $projectId);
    }

    /** فقط پروژه‌های مجاز وارد داده‌ی پروفایل و پاسخ زنده می‌شوند. */
    public static function visible(int $uid): array
    {
        $rows = DB::all("SELECT p.id, p.pid, p.title FROM projects p WHERE p.blocked_at IS NULL AND (p.user_id = ? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?) OR EXISTS (SELECT 1 FROM users a WHERE a.id = ? AND a.role = 'admin')) ORDER BY p.title, p.id", [$uid, $uid, $uid]);
        $out = [];
        foreach ($rows as $p) {
            if (Access::canViewStorage((int) $p['id'], $uid)) {
                $out[] = ['pid' => $p['pid'], 'title' => $p['title']] + self::status((int) $p['id']);
            }
        }
        return $out;
    }

    public static function setLimit(int $projectId, ?int $mb): void
    {
        $pdo = self::connection();
        $lock = self::lockName('project', $projectId);
        self::lock($pdo, $lock);
        try {
            DB::update('projects', ['storage_limit_mb' => $mb], 'id = ?', [$projectId]);
        } finally { self::unlock($pdo, $lock); }
    }

    /** اتصال مستقل باعث می‌شود snapshot یا rollback فرم، حساب فایل روی دیسک را از بین نبرد. */
    public static function register(int $uid, string $type, string $name, ?int $projectId = null, ?int $taskId = null): ?string
    {
        $pdo = self::connection();
        $taskLock = $taskId ? self::lockName('task', $taskId) : null;
        $projectLock = null;
        $heldTask = false;
        $keepTask = false;
        try {
            if ($taskLock !== null) {
                self::lock($pdo, $taskLock);
                $heldTask = true;
                $t = self::query($pdo, 'SELECT project_id FROM tasks WHERE id = ?', [$taskId])->fetch();
                if ($t) { $projectId = $t['project_id'] === null ? null : (int) $t['project_id']; }
            }
            if ($projectId !== null) {
                $projectLock = self::lockName('project', $projectId);
                self::lock($pdo, $projectLock);
            }
            $path = Uploader::path($type, $name);
            if ($path === null) { return ErrorText::apply(self::BUSY); }
            clearstatcache(true, $path);
            $size = filesize($path);
            if ($size === false || $size <= 0) { return ErrorText::apply(self::BUSY); }
            if ($projectId !== null) {
                $p = self::query($pdo, 'SELECT storage_limit_mb FROM projects WHERE id = ?', [$projectId])->fetch();
                if (!$p) { return ErrorText::apply(self::BUSY); }
                $limit = $p['storage_limit_mb'] === null ? null : (int) $p['storage_limit_mb'] * self::MB;
                if (!self::fits(self::sum($pdo, $projectId), $size, $limit)) { return ErrorText::apply(self::FULL); }
            }
            self::query($pdo, 'INSERT INTO storage_files (user_id, project_id, directory, file_name, bytes) VALUES (?, ?, ?, ?, ?)', [$uid, $projectId, $type, $name, $size]);
            \App\Core\AdminLog::inserted('storage_files', (int) $pdo->lastInsertId(), ['id' => (int) $pdo->lastInsertId(), 'user_id' => $uid, 'project_id' => $projectId, 'directory' => $type, 'file_name' => $name, 'bytes' => $size]);
            if ($heldTask) {
                // انتقال پروژه تا ثبت پیوست و پایان تراکنش فرم منتظر می‌ماند.
                register_shutdown_function(static function () use ($pdo, $taskLock): void {
                    try { self::unlock($pdo, $taskLock); } catch (\Throwable $e) { /* اتصال بسته شده است */ }
                });
                $keepTask = true;
            }
            return null;
        } finally {
            if ($projectLock !== null) { self::unlock($pdo, $projectLock); }
            if ($heldTask && !$keepTask) { self::unlock($pdo, $taskLock); }
        }
    }

    public static function fits(int $used, int $incoming, ?int $limit): bool
    {
        return $incoming > 0 && ($limit === null || $incoming <= max(0, $limit - $used));
    }

    /** انتقال تسک، پیوست‌های تسک و یادداشت‌هایش را با کنترل سقف مقصد جابه‌جا می‌کند. */
    public static function moveTask(int $taskId, int $target, array $changes): ?string
    {
        $pdo = self::connection();
        $locks = [];
        $keep = false;
        try {
            $key = self::lockName('task', $taskId);
            self::lock($pdo, $key); $locks[] = $key;
            $source = (int) self::query($pdo, 'SELECT project_id FROM tasks WHERE id = ?', [$taskId])->fetchColumn();
            $ids = array_unique(array_filter([$source, $target])); sort($ids);
            foreach ($ids as $id) { $key = self::lockName('project', $id); self::lock($pdo, $key); $locks[] = $key; }
            $files = self::query($pdo, "SELECT f.id, f.directory, f.file_name, f.project_id FROM storage_files f WHERE (f.directory = 'task_attachments' AND f.file_name IN (SELECT a.file_path FROM task_attachments a WHERE a.task_id = ?)) OR (f.directory = 'note_attachments' AND f.file_name IN (SELECT a.file_path FROM note_attachments a INNER JOIN task_notes n ON n.id = a.note_id WHERE n.task_id = ?))", [$taskId, $taskId])->fetchAll();
            $incoming = 0;
            foreach ($files as $f) {
                $path = Uploader::path($f['directory'], $f['file_name']);
                if ($path !== null && (int) $f['project_id'] !== $target) { $incoming += (int) filesize($path); }
            }
            $p = self::query($pdo, 'SELECT storage_limit_mb FROM projects WHERE id = ?', [$target])->fetch();
            if (!$p) { return ErrorText::apply(self::BUSY); }
            $limit = $p['storage_limit_mb'] === null ? null : (int) $p['storage_limit_mb'] * self::MB;
            if ($incoming > 0 && !self::fits(self::sum($pdo, $target), $incoming, $limit)) { return ErrorText::apply(self::FULL); }
            $nested = DB::pdo()->inTransaction();
            DB::transaction(static function () use ($files, $taskId, $target, $changes): void {
                foreach ($files as $f) { DB::update('storage_files', ['project_id' => $target], 'id = ?', [(int) $f['id']]); }
                DB::update('tasks', $changes, 'id = ?', [$taskId]);
            });
            if ($nested) {
                register_shutdown_function(static function () use ($pdo, $locks): void {
                    foreach (array_reverse($locks) as $key) { try { self::unlock($pdo, $key); } catch (\Throwable $e) { /* اتصال بسته شده است */ } }
                });
                $keep = true;
            }
            return null;
        } finally {
            if (!$keep) { foreach (array_reverse($locks) as $key) { self::unlock($pdo, $key); } }
        }
    }

    /** سهمیه‌های قدیمی هر سازنده یک‌بار برای پروژه‌های موجود او کپی می‌شوند؛ پروژه‌ی جدید نامحدود است. */
    public static function migrateProjects(): void
    {
        $v = DB::value('SELECT body FROM content_texts WHERE `key` = ?', ['storage.quota.default']);
        $default = $v === null || $v === 'null' ? null : (int) $v;
        DB::run('UPDATE projects p INNER JOIN users u ON u.id = p.user_id SET p.storage_limit_mb = IF(u.storage_custom = 1, u.storage_limit_mb, ?)', [$default]);
        DB::run("UPDATE storage_files f INNER JOIN task_attachments a ON f.directory = 'task_attachments' AND f.file_name = a.file_path INNER JOIN tasks t ON t.id = a.task_id SET f.project_id = t.project_id");
        DB::run("UPDATE storage_files f INNER JOIN note_attachments a ON f.directory = 'note_attachments' AND f.file_name = a.file_path INNER JOIN task_notes n ON n.id = a.note_id INNER JOIN tasks t ON t.id = n.task_id SET f.project_id = t.project_id");
        DB::run("UPDATE storage_files f INNER JOIN projects p ON f.directory = 'projects' AND f.file_name = p.profile_image SET f.project_id = p.id");
        // نقش‌های مدیر که همه‌ی مجوزهای قبلی را داشتند، با اضافه‌شدن کلید جدید سفارشی نمی‌شوند.
        $old = array_values(array_diff(array_keys(Access::PROJECT_PERMS), ['view_storage']));
        foreach (DB::all('SELECT id, permissions FROM project_members') as $member) {
            $perms = json_decode((string) $member['permissions'], true);
            if (is_array($perms) && !array_diff($old, $perms) && !in_array('view_storage', $perms, true)) {
                $perms[] = 'view_storage';
                DB::update('project_members', ['permissions' => json_encode($perms)], 'id = ?', [(int) $member['id']]);
            }
        }

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
