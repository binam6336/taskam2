<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Crypto;
use App\Core\ContentCipher;
use App\Core\DB;
use App\Core\HttpException;

/** دفتر پنل‌های جانبی؛ هر ردیف مستقل و تمام محتوای آن با کلید موجود رمزگذاری می‌شود. */
final class SidePanels
{
    public const PREFIX = 'admin.side_panel.';
    public const MAX_PANELS = 200;

    private static function key(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{24}$/D', $id)) { throw new HttpException(404, 'پنل جانبی یافت نشد.'); }
        return self::PREFIX . $id;
    }

    private static function decode(string $body, string $key): array
    {
        $plain = Crypto::decode($body, ContentCipher::key(), $key);
        $data = $plain === null ? null : json_decode($plain, true);
        if (!is_array($data) || !isset($data['title'], $data['url'], $data['description'], $data['username'], $data['password'])) {
            throw new HttpException(422, 'اطلاعات پنل جانبی رمزگشایی نشد؛ کلید رمزگذاری را بررسی کنید.');
        }
        return $data;
    }

    public static function listing(): array
    {
        $out = [];
        foreach (DB::all('SELECT `key`, body FROM content_texts WHERE `key` LIKE ? ORDER BY `key`', [substr(DB::like(self::PREFIX), 1)]) as $r) {
            $id = substr($r['key'], strlen(self::PREFIX));
            try {
                $d = self::decode($r['body'], $r['key']);
                $out[] = ['id' => $id, 'title' => $d['title'], 'url' => $d['url'], 'description' => $d['description'],
                    'has_username' => $d['username'] !== '', 'has_password' => $d['password'] !== '', 'unavailable' => false];
            } catch (HttpException $e) {
                $out[] = ['id' => $id, 'unavailable' => true];
            }
        }
        return $out;
    }

    public static function credentials(string $id): array
    {
        $key = self::key($id);
        $body = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [$key]);
        if ($body === null) { throw new HttpException(404, 'پنل جانبی یافت نشد.'); }
        $d = self::decode($body, $key);
        return ['username' => $d['username'], 'password' => $d['password']];
    }

    public static function save(?string $id, array $data, int $uid): string
    {
        if (!ContentCipher::enabled()) { throw new HttpException(422, 'رمزگذاری آماده نیست؛ کلید فعلی رمزگذاری و OpenSSL را بررسی کنید.'); }
        $limits = ['title' => 150, 'url' => 2048, 'description' => 2000, 'username' => 500, 'password' => 500];
        foreach ($limits as $k => $limit) {
            if (!isset($data[$k]) || !is_string($data[$k]) || mb_strlen($data[$k]) > $limit) {
                throw new HttpException(422, 'اطلاعات پنل جانبی نامعتبر یا طولانی است.');
            }
        }
        foreach (['title', 'url', 'description'] as $k) { $data[$k] = trim($data[$k]); }
        $url = parse_url($data['url']);
        if ($data['title'] === '' || !filter_var($data['url'], FILTER_VALIDATE_URL) || !$url
            || !in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            || isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20\x7f]/', $data['url'])) {
            throw new HttpException(422, 'عنوان و نشانی معتبر http یا https بدون اطلاعات ورود لازم است.');
        }
        $new = $id === null;
        $id = $id ?? bin2hex(random_bytes(12));
        $key = self::key($id);
        DB::transaction(static function () use ($new, $key, $data, $uid): void {
            if (!$new) {
                $body = DB::value('SELECT body FROM content_texts WHERE `key` = ? FOR UPDATE', [$key]);
                if ($body === null) { throw new HttpException(404, 'پنل جانبی یافت نشد.'); }
                $old = self::decode($body, $key);
                foreach (['username', 'password'] as $field) {
                    if (!empty($data['clear_' . $field])) { $data[$field] = ''; }
                    elseif ($data[$field] === '') { $data[$field] = $old[$field]; }
                }
            } elseif ((int) DB::value('SELECT COUNT(*) FROM content_texts WHERE `key` LIKE ?', [substr(DB::like(self::PREFIX), 1)]) >= self::MAX_PANELS) {
                throw new HttpException(422, 'سقف تعداد پنل‌های جانبی تکمیل شده است.');
            }
            $record = array_intersect_key($data, array_flip(['title', 'url', 'description', 'username', 'password']));
            $encrypted = Crypto::encode(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ContentCipher::key(), $key);
            if ($new) { DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?)', [$key, $encrypted, $uid]); }
            else { DB::run('UPDATE content_texts SET body = ?, updated_by = ? WHERE `key` = ?', [$encrypted, $uid, $key]); }
        });
        return $id;
    }

    public static function delete(string $id): void
    {
        if (!DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::key($id)])) {
            throw new HttpException(404, 'پنل جانبی یافت نشد.');
        }
    }
}
