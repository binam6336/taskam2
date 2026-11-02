<?php
declare(strict_types=1);

namespace App\Core;

/**
 * سیستم انکد/دیکد با رمز (.env → ENCODE_PASSWORD).
 *
 *  • رمزنگاری احرازشده‌ی AES-256-GCM  → هر دست‌کاری یا رمز اشتباه، «دیکد ناموفق» (null) می‌دهد؛ هرگز متن اشتباه برنمی‌گرداند
 *  • کلید از رمز با PBKDF2-HMAC-SHA256 ساخته می‌شود (تعداد تکرار داخل خود خروجی ذخیره است؛ تغییر ENCODE_KDF_ITER داده‌های قبلی را خراب نمی‌کند)
 *  • برای هر متن یک nonce تصادفی ۹۶ بیتی؛ پس انکد یک متن هر بار خروجی متفاوت دارد
 *  • AAD (داده‌ی همراه): انکد را به یک «زمینه» (مثلاً فرستنده/گیرنده‌ی پیام) می‌بندد تا ciphertext را نتوان به رکورد دیگر منتقل کرد
 *
 * قالب خروجی:  enc:v1:<base64url( iter[4B big-endian] ‖ iv[12B] ‖ tag[16B] ‖ ciphertext )>
 */
final class Crypto
{
    public const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const SALT = 'taskam.crypto.v1';
    private const MIN_PASSWORD = 12;
    private const MAX_ITER = 2000000;
    /** حداکثر کلید متفاوت (رمز × تعداد تکرار) که در یک درخواست ساخته می‌شود */
    private const MAX_KEYS_PER_REQUEST = 4;

    /** @var array<string,string> */
    private static $keyCache = [];

    /** رمز تنظیم‌شده در .env (هر دو نام ENCODE_PASSWORD و encode_password پذیرفته می‌شود). */
    public static function envPassword(): string
    {
        foreach (['ENCODE_PASSWORD', 'encode_password'] as $k) {
            $v = Env::get($k);
            if (is_string($v) && $v !== '') {
                return $v;
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
        }
        return '';
    }

    /** آیا رمز معتبر (حداقل ۱۲ نویسه) تنظیم شده و OpenSSL از AES-GCM پشتیبانی می‌کند؟ */
    public static function configured(?string $password = null): bool
    {
        $p = $password ?? self::envPassword();
        return strlen($p) >= self::MIN_PASSWORD && self::available();
    }

    public static function available(): bool
    {
        return function_exists('openssl_encrypt') && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    public static function isEncoded(?string $s): bool
    {
        return $s !== null && strncmp($s, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    private static function iterations(): int
    {
        $i = Env::get('ENCODE_KDF_ITER');
        return max(1000, min(self::MAX_ITER, is_numeric($i) ? (int) $i : 20000));
    }

    /**
     * تعداد تکرار خوانده‌شده از خود ciphertext فقط از فهرست مقادیر شناخته‌شده پذیرفته می‌شود:
     * مقدار فعلی ENCODE_KDF_ITER، پیش‌فرض ۲۰۰۰۰ و مقادیر قبلی در ENCODE_KDF_ITER_OLD (با کاما) — نه هر عددی تا ۲ میلیون.
     */
    private static function iterAllowed(int $iter): bool
    {
        static $allowed = null;
        if ($allowed === null) {
            $allowed = [self::iterations() => true, 20000 => true];
            foreach (explode(',', (string) Env::get('ENCODE_KDF_ITER_OLD', '')) as $v) {
                if (is_numeric(trim($v))) {
                    $allowed[(int) trim($v)] = true;
                }
            }
        }
        return isset($allowed[$iter]);
    }

    private static function key(string $password, int $iter): string
    {
        $id = hash('sha256', $iter . '|' . $password);
        if (!isset(self::$keyCache[$id])) {
            // هر تعداد تکرار متفاوت = یک PBKDF2 کامل؛ ردیف‌های دست‌کاری‌شده با تکرارهای گوناگون نباید CPU را قفل کنند
            if (count(self::$keyCache) >= self::MAX_KEYS_PER_REQUEST) {
                throw new \RuntimeException('Crypto: تعداد کلیدهای متفاوت در یک درخواست بیش از حد است.');
            }
            self::$keyCache[$id] = hash_pbkdf2('sha256', $password, self::SALT, $iter, 32, true);
        }
        return self::$keyCache[$id];
    }

    /**
     * متن را انکد می‌کند.
     * @throws \RuntimeException اگر رمز معتبر نباشد یا OpenSSL خطا بدهد (هیچ‌وقت بی‌صدا متن ساده برنمی‌گرداند)
     */
    public static function encode(string $plain, ?string $password = null, string $aad = '', ?int $iter = null): string
    {
        $password = $password ?? self::envPassword();
        if (!self::configured($password)) {
            throw new \RuntimeException('Crypto: رمز انکد (ENCODE_PASSWORD) تنظیم نشده یا کمتر از ۱۲ نویسه است، یا OpenSSL فعال نیست.');
        }
        $iter = $iter ?? self::iterations();
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, self::CIPHER, self::key($password, $iter), OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($ct === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Crypto: رمزنگاری ناموفق بود.');
        }
        return self::PREFIX . rtrim(strtr(base64_encode(pack('N', $iter) . $iv . $tag . $ct), '+/', '-_'), '=');
    }

    /**
     * متن انکدشده را با همان رمز دیکد می‌کند.
     * @return string|null  متن اصلی؛ اگر رمز اشتباه باشد، متن دست‌کاری شده باشد، AAD متفاوت باشد یا قالب نامعتبر باشد → null
     */
    public static function decode(string $token, ?string $password = null, string $aad = ''): ?string
    {
        $password = $password ?? self::envPassword();
        if (!self::isEncoded($token) || strlen($password) < self::MIN_PASSWORD || !self::available()) {
            return null;
        }
        $b64 = substr($token, strlen(self::PREFIX));
        $raw = base64_decode(strtr($b64, '-_', '+/') . str_repeat('=', (4 - strlen($b64) % 4) % 4), true);
        if ($raw === false || strlen($raw) < 4 + 12 + 16) {
            return null;
        }
        $iter = (int) unpack('N', substr($raw, 0, 4))[1];
        if ($iter < 1000 || $iter > self::MAX_ITER || !self::iterAllowed($iter)) {
            return null;
        }
        $iv = substr($raw, 4, 12);
        $tag = substr($raw, 16, 16);
        $ct = (string) substr($raw, 32);
        try {
            $key = self::key($password, $iter);
        } catch (\RuntimeException $e) {
            return null;
        }
        $plain = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        return $plain === false ? null : $plain;
    }

    /** برای داده‌های قدیمی: اگر انکد نبود همان را برمی‌گرداند؛ اگر انکد بود و دیکد نشد null. */
    public static function decodeOrPlain(string $value, ?string $password = null, string $aad = ''): ?string
    {
        return self::isEncoded($value) ? self::decode($value, $password, $aad) : $value;
    }

    /** بردار تست شناخته‌شده (ساخته‌شده با پیاده‌سازی مستقل Python) برای تأیید سازگاری روی سرور. */
    public const VECTOR_PASSWORD = 'taskam-selftest-password';
    public const VECTOR_AAD = 'chat:1:2';
    public const VECTOR_PLAIN = 'سلام دنیا – test 123 ✅';
    public const VECTOR_TOKEN = 'enc:v1:AAAD6MPG_dvP4jUHEjIXZFqikOQ-8of_p54O0QfUkS81goAn_hawfWhPMMJF9etrttA4MfusXNpwp8n3U5XUkcDz';

    /** @return array<int,array{0:string,1:bool}> نتیجه‌ی آزمون‌های خودکار [شرح, موفق؟] */
    public static function selfTest(?string $password = null): array
    {
        $out = [];
        $out[] = ['OpenSSL و AES-256-GCM در دسترس است', self::available()];
        $pw = $password ?? self::VECTOR_PASSWORD;
        try {
            $t = self::encode(self::VECTOR_PLAIN, $pw, self::VECTOR_AAD, 1000);
            $out[] = ['انکد/دیکد با رمز درست، متن اصلی را برمی‌گرداند', self::decode($t, $pw, self::VECTOR_AAD) === self::VECTOR_PLAIN];
            $out[] = ['رمز اشتباه → دیکد ناموفق (null)', self::decode($t, $pw . 'x', self::VECTOR_AAD) === null];
            $out[] = ['زمینه (AAD) متفاوت → دیکد ناموفق', self::decode($t, $pw, 'chat:2:1') === null];
            $flip = $t;
            $flip[strlen($flip) - 3] = $flip[strlen($flip) - 3] === 'A' ? 'B' : 'A';
            $out[] = ['دست‌کاری خروجی → دیکد ناموفق', self::decode($flip, $pw, self::VECTOR_AAD) === null];
            $out[] = ['دو انکد از یک متن، خروجی متفاوت دارند (nonce تصادفی)', $t !== self::encode(self::VECTOR_PLAIN, $pw, self::VECTOR_AAD, 1000)];
            $out[] = ['بردار شناخته‌شده‌ی ساخته‌شده با پیاده‌سازی مستقل (Python) دیکد می‌شود', self::decode(self::VECTOR_TOKEN, self::VECTOR_PASSWORD, self::VECTOR_AAD) === self::VECTOR_PLAIN];
        } catch (\Throwable $e) {
            $out[] = ['خطا در آزمون: ' . $e->getMessage(), false];
        }
        return $out;
    }
}
