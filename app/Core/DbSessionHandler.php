<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ذخیره‌ی نشست‌ها در جدول user_sessions (به‌جای فایل).
 * - شناسه‌ی نشست (کوکی taskam_sid) ذخیره نمی‌شود؛ فقط هش SHA-256 آن. نشت دیتابیس/بکاپ، کوکی قابل‌استفاده‌ای نمی‌دهد.
 * - هر نشست واردشده به دستگاه ورود گره می‌خورد (ip، ip_prefix = دو بخش اول IP، user_agent)؛ بررسی در Auth::user.
 * - قفل MySQL (GET_LOCK) مثل قفل فایل جلوی بازنویسی هم‌زمان نشست را می‌گیرد.
 * - با چرخش/بازسازی شناسه، اتصال دستگاه به شناسه‌ی جدید منتقل می‌شود.
 */
final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    /** ردیف خوانده‌شده در همین درخواست: ['ip','ip_prefix','user_agent','user_id'] */
    private static ?array $row = null;
    /** اتصال تازه‌ی دستگاه (هنگام ورود) */
    private static ?array $bind = null;
    private ?string $lock = null;

    public static function hash(string $id): string
    {
        return hash('sha256', $id);
    }

    /** اتصال نشست فعلی به دستگاه (در Auth::login) */
    public static function bind(string $ip, string $prefix, string $ua): void
    {
        self::$bind = ['ip' => $ip, 'ip_prefix' => $prefix, 'user_agent' => mb_substr($ua, 0, 255)];
        self::$row = self::$bind + ['user_id' => self::$row['user_id'] ?? null];
    }

    /** اتصال دستگاه نشست فعلی (یا null اگر هنوز ثبت نشده) */
    public static function binding(): ?array
    {
        return self::$row !== null && self::$row['ip_prefix'] !== null ? self::$row : null;
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name)
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        if ($this->lock !== null) {
            try {
                DB::value('SELECT RELEASE_LOCK(?)', [$this->lock]);
            } catch (\Throwable $e) {
            }
            $this->lock = null;
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        $h = self::hash((string) $id);
        $this->lock = 'tks_' . substr($h, 0, 40);
        DB::value('SELECT GET_LOCK(?, 10)', [$this->lock]);
        $r = DB::one('SELECT data, user_id, ip, ip_prefix, user_agent FROM user_sessions WHERE id_hash = ?', [$h]);
        if ($r === null) {
            return '';                                  // ردیف قبلی (در چرخش شناسه) در self::$row می‌ماند تا اتصال منتقل شود
        }
        self::$row = ['ip' => $r['ip'], 'ip_prefix' => $r['ip_prefix'], 'user_agent' => $r['user_agent'], 'user_id' => $r['user_id']];
        return (string) $r['data'];
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        $uid = isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0 ? (int) $_SESSION['uid'] : null;
        $life = (int) ($_SESSION['_life'] ?? 0);
        $b = $uid !== null ? (self::$bind ?? self::$row) : null;      // مهمان اتصال دستگاه ندارد
        $sql = 'INSERT INTO user_sessions (id_hash, user_id, data, ip, ip_prefix, user_agent, life, last_activity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), data = VALUES(data), life = VALUES(life), last_activity = VALUES(last_activity)';
        if (self::$bind !== null || $uid === null) {
            $sql .= ', ip = VALUES(ip), ip_prefix = VALUES(ip_prefix), user_agent = VALUES(user_agent)';
        }
        DB::run($sql, [self::hash((string) $id), $uid, (string) $data, $b['ip'] ?? null, $b['ip_prefix'] ?? null, $b['user_agent'] ?? null, $life, time()]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
        DB::run('DELETE FROM user_sessions WHERE id_hash = ?', [self::hash((string) $id)]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($max)
    {
        return self::sweep();
    }

    #[\ReturnTypeWillChange]
    public function validateId($id)
    {
        return (bool) DB::value('SELECT 1 FROM user_sessions WHERE id_hash = ?', [self::hash((string) $id)]);
    }

    #[\ReturnTypeWillChange]
    public function updateTimestamp($id, $data)
    {
        return $this->write($id, $data);
    }

    /** پاک‌سازی: مهمان بعد از ۲ ساعت، نشست منقضی بعد از life، همه بعد از سقف «مرا به خاطر بسپار» */
    public static function sweep(): int
    {
        $now = time();
        return DB::run('DELETE FROM user_sessions WHERE (user_id IS NULL AND last_activity < ?) OR (life > 0 AND last_activity + life + 3600 < ?) OR last_activity < ?',
            [$now - 7200, $now, $now - Session::rememberSeconds() - 86400]);
    }

    /** نشست‌های دیگر یک کاربر (همه به‌جز نشست فعلی) */
    public static function destroyOthers(int $uid, string $currentId): void
    {
        DB::run('DELETE FROM user_sessions WHERE user_id = ? AND id_hash <> ?', [$uid, self::hash($currentId)]);
    }
}
