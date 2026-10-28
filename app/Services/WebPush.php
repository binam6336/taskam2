<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ارسال Web Push بدون کتابخانه‌ی خارجی (فقط OpenSSL):
 *  - احراز هویت سرور با VAPID (RFC 8292، امضای ES256)
 *  - رمزگذاری محتوا با aes128gcm (RFC 8291 / RFC 8188)
 * کلیدهای VAPID از .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY) یا در اولین استفاده ساخته و در storage/vapid.json ذخیره می‌شوند.
 */
final class WebPush
{
    /** پیشوند DER کلید عمومی P-256 (SubjectPublicKeyInfo) — بعد از آن ۶۵ بایت 0x04||X||Y می‌آید */
    private const P256_SPKI = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    public static function supported(): bool
    {
        return !array_filter(self::requirements(), static fn($r) => !$r[1]);
    }

    /**
     * پیش‌نیازهای سرور برای ارسال push، هر کدام جدا (برای نمایش علت دقیق).
     * @return array<string,array{0:string,1:bool,2:string}> کلید => [عنوان، برقرار است؟، راه‌حل]
     */
    public static function requirements(): array
    {
        $ssl = extension_loaded('openssl');
        $gcm = false;
        if ($ssl) {
            // به‌جای جستجوی نام در فهرست (که در نسخه‌های مختلف OpenSSL فرق دارد) واقعاً رمزگذاری را امتحان می‌کنیم
            $tag = '';
            $gcm = @openssl_encrypt('t', 'aes-128-gcm', str_repeat('k', 16), OPENSSL_RAW_DATA, str_repeat('n', 12), $tag) !== false && strlen((string) $tag) === 16;
        }
        $ec = false;
        if ($ssl && function_exists('openssl_pkey_derive')) {
            try {
                $ec = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) !== false;
            } catch (\Throwable $e) {
                $ec = false;
            }
        }
        return [
            'openssl' => ['افزونه‌ی PHP «openssl»', $ssl, 'از پنل هاست (Select PHP Version ← Extensions) افزونه‌ی openssl را فعال کنید.'],
            'derive'  => ['تابع openssl_pkey_derive (PHP 7.3 به بالا)', $ssl && function_exists('openssl_pkey_derive'), 'نسخه‌ی PHP را به 8.1 یا بالاتر ارتقا دهید (یا این تابع در disable_functions غیرفعال شده است).'],
            'ec'      => ['ساخت کلید منحنی بیضوی P-256', $ec, 'کتابخانه‌ی OpenSSL سرور از منحنی prime256v1 پشتیبانی نمی‌کند یا فایل openssl.cnf در دسترس نیست؛ از پشتیبانی هاست بخواهید OpenSSL را اصلاح کند.'],
            'hkdf'    => ['تابع hash_hkdf', function_exists('hash_hkdf'), 'نسخه‌ی PHP را به 7.4 یا بالاتر ارتقا دهید.'],
            'gcm'     => ['رمزگذاری AES-128-GCM', $gcm, 'نسخه‌ی OpenSSL سرور از AES-GCM پشتیبانی نمی‌کند؛ PHP/OpenSSL را به‌روز کنید.'],
        ];
    }

    /** یک جفت‌کلید P-256: [pem خصوصی, کلید عمومی خام ۶۵ بایتی] */
    private static function newKey(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($k === false) {
            throw new \RuntimeException('OpenSSL نتوانست کلید EC بسازد.');
        }
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k)['ec'];
        return [$pem, "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT), $k];
    }

    /** @return array{public:string,private_pem:string} public = base64url برای applicationServerKey */
    public static function vapid(): array
    {
        static $v = null;
        if ($v !== null) {
            return $v;
        }
        $pub = (string) env('VAPID_PUBLIC_KEY', '');
        $priv = (string) env('VAPID_PRIVATE_KEY', '');
        if ($pub !== '' && $priv !== '') {
            $pem = str_contains($priv, 'BEGIN') ? str_replace('\n', "\n", $priv) : self::pemFromRaw(self::unb64u($priv), self::unb64u($pub));
            return $v = ['public' => $pub, 'private_pem' => $pem];
        }
        $file = STORAGE_PATH . '/vapid.json';
        if (is_file($file)) {
            $j = json_decode((string) file_get_contents($file), true);
            if (!empty($j['public']) && !empty($j['private_pem'])) {
                return $v = $j;
            }
        }
        [$pem, $raw] = self::newKey();
        $v = ['public' => self::b64u($raw), 'private_pem' => $pem, 'created_at' => date('c')];
        @file_put_contents($file, json_encode($v, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($file, 0600);
        return $v;
    }

    /** ساخت PEM از کلید خصوصی خام ۳۲ بایتی (فرمت رایج VAPID در ابزارهای دیگر) */
    private static function pemFromRaw(string $d, string $pub): string
    {
        // ECPrivateKey (RFC 5915) با پارامتر prime256v1 و کلید عمومی
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $pub;
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** امضای DER ECDSA → فرمت خام R||S (۶۴ بایت) برای JWT */
    private static function derToRaw(string $der): string
    {
        $pos = 3;
        $rLen = ord($der[$pos]);
        $r = substr($der, $pos + 1, $rLen);
        $pos += 1 + $rLen + 1;
        $sLen = ord($der[$pos]);
        $s = substr($der, $pos + 1, $sLen);
        $fix = static fn(string $x): string => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);
        return $fix($r) . $fix($s);
    }

    private static function jwt(string $endpoint): string
    {
        $u = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $sub = (string) env('VAPID_SUBJECT', '');
        if ($sub === '') {
            $sub = 'mailto:admin@' . preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        $h = self::b64u('{"typ":"JWT","alg":"ES256"}');
        $c = self::b64u((string) json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $sub], JSON_UNESCAPED_SLASHES));
        openssl_sign($h . '.' . $c, $sig, self::vapid()['private_pem'], OPENSSL_ALGO_SHA256);
        return $h . '.' . $c . '.' . self::b64u(self::derToRaw($sig));
    }

    /** رمزگذاری aes128gcm محتوا برای یک اشتراک (RFC 8291) */
    public static function encrypt(string $payload, string $p256dhB64u, string $authB64u): string
    {
        $uaPub = self::unb64u($p256dhB64u);
        $auth = self::unb64u($authB64u);
        if (strlen($uaPub) !== 65 || strlen($auth) < 16) {
            throw new \InvalidArgumentException('کلیدهای اشتراک نامعتبر است.');
        }
        [, $asPub, $asKey] = self::newKey();
        $peer = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::P256_SPKI) . $uaPub), 64, "\n") . "-----END PUBLIC KEY-----\n");
        $ecdh = openssl_pkey_derive($peer, $asKey, 32);
        if ($ecdh === false) {
            throw new \RuntimeException('ECDH ناموفق بود.');
        }
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
    }

    /**
     * ارسال یک پیام. خروجی: کد HTTP سرویس push (۲۰۱ = موفق، ۴۰۴/۴۱۰ = اشتراک منقضی) یا ۰ برای خطای شبکه.
     * @param array{endpoint:string,p256dh:string,auth:string} $sub
     */
    public static function send(array $sub, array $data, int $ttl = 86400, string $urgency = 'high', ?string $topic = null): int
    {
        $body = self::encrypt((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sub['p256dh'], $sub['auth']);
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Urgency: ' . $urgency,
            'Authorization: vapid t=' . self::jwt($sub['endpoint']) . ', k=' . self::vapid()['public'],
        ];
        if ($topic !== null && $topic !== '') {
            $headers[] = 'Topic: ' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $topic), 0, 32);   // پیام جدیدتر با همین topic جایگزین قبلی می‌شود
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($sub['endpoint']);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return $code;
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 8, 'ignore_errors' => true]]);
        @file_get_contents($sub['endpoint'], false, $ctx);
        return isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    }
}
