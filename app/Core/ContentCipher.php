<?php
declare(strict_types=1);
namespace App\Core;

/** محتوای آزاد؛ کلید مستقل از کلید قدیمی چت تا پیام‌های قبلی خراب نشوند. */
final class ContentCipher
{
    public const FIELDS = ['tasks' => ['title','description'], 'projects' => ['description'], 'texts' => ['title','content'], 'task_notes' => ['note'], 'support_messages' => ['message']];
    public const PREFIX = 'data:v1:';
    private static bool $verified = false;
    public static function key(): string { return (string) env('decode_key', ''); }
    public static function enabled(): bool { return strlen(self::key()) >= 12 && Crypto::available(); }
    public static function seal(string $value): string
    {
        if (!self::enabled()) { throw new \RuntimeException('decode_key must contain at least 12 bytes and OpenSSL must be available'); }
        if (!self::$verified) {
            foreach (self::FIELDS as $table => $cols) {
                foreach ($cols as $col) {
                    $v = DB::pdo()->query("SELECT `$col` FROM `$table` WHERE `$col` LIKE 'data:v1:%' LIMIT 1")->fetchColumn();
                    if ($v !== false) { self::open($v); break 2; }
                }
            }
            self::$verified = true;
        }
        return self::PREFIX . Crypto::encode($value, self::key(), 'karlix-content-v1');
    }
    public static function open($value)
    {
        if (is_array($value)) { return array_map([self::class,'open'], $value); }
        if (!is_string($value) || !str_starts_with($value, self::PREFIX)) { return $value; }
        $plain = Crypto::decode(substr($value, strlen(self::PREFIX)), self::key(), 'karlix-content-v1');
        if ($plain === null) { throw new \RuntimeException('Content decryption failed; verify decode_key'); }
        return $plain;
    }
    public static function prepare(string $table, array $data): array
    {
        if (self::key() === '') { return $data; }
        foreach (self::FIELDS[$table] ?? [] as $col) {
            if (isset($data[$col]) && $data[$col] !== '') { $data[$col] = self::seal((string) $data[$col]); }
        }
        return $data;
    }
    /** تطابق روی متن رمزگشایی‌شده؛ فقط ciphertext وارد شرط SQL می‌شود و فیلتر دسترسی همان کوئری حفظ می‌شود. */
    public static function matches(string $token): array
    {
        $out = [];
        foreach (self::FIELDS as $table => $cols) {
            foreach ($cols as $col) {
                $st = DB::pdo()->query("SELECT DISTINCT `$col` FROM `$table` WHERE `$col` LIKE 'data:v1:%'");
                while (($v = $st->fetchColumn()) !== false) {
                    if (mb_strpos(Search::norm(self::open($v)), $token) !== false) { $out[] = $v; }
                }
            }
        }
        return array_values(array_unique($out));
    }
    public static function pending(): int
    {
        $n = 0;
        foreach (self::FIELDS as $table => $cols) { foreach ($cols as $col) { $n += (int) DB::value("SELECT COUNT(*) FROM `$table` WHERE `$col` IS NOT NULL AND `$col` <> '' AND `$col` NOT LIKE 'data:v1:%'"); } }
        return $n;
    }
    public static function batch(int $limit = 100): int
    {
        if (!self::enabled()) { throw new \RuntimeException('decode_key is not configured'); }
        $done = 0;
        foreach (self::FIELDS as $table => $cols) { foreach ($cols as $col) {
            $st = DB::pdo()->query("SELECT id, `$col` FROM `$table` WHERE `$col` IS NOT NULL AND `$col` <> '' AND `$col` NOT LIKE 'data:v1:%' LIMIT " . max(1,$limit-$done));
            foreach ($st->fetchAll() as $row) {
                // مقایسه با مقدار قبلی مانع بازنویسی ویرایش هم‌زمان می‌شود.
                $q = DB::pdo()->prepare("UPDATE `$table` SET `$col` = ? WHERE id = ? AND `$col` = ?");
                $q->execute([self::seal($row[$col]),$row['id'],$row[$col]]); $done += $q->rowCount();
            }
            if ($done >= $limit) { return $done; }
        } }
        return $done;
    }
}
