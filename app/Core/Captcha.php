<?php
declare(strict_types=1);

namespace App\Core;

/**
 * کپچای تصویری صفحه‌ی ورود: ۵ نویسه از حروف بزرگ انگلیسی و رقم (بدون نویسه‌های شبیه به هم مثل O/0 و I/1).
 * کد فقط به‌صورت هش در نشست ذخیره می‌شود، یک‌بارمصرف است و بعد از EXPIRE ثانیه باطل می‌شود.
 * مقایسه حساس به حروف است: فقط حروف بزرگ پذیرفته می‌شود (فیلد ورود خودش حروف را بزرگ می‌کند).
 * تصویر با فونت داخلی GD ساخته می‌شود (بدون نیاز به فایل فونت روی هاست).
 */
final class Captcha
{
    public const LENGTH = 5;
    public const EXPIRE = 600;
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const KEY = '_captcha';

    /** کپچای ورود فعال است؟ (CAPTCHA_LOGIN=false فقط برای محیط توسعه/تست خودکار) */
    public static function enabled(): bool
    {
        return !in_array(strtolower(trim((string) env('CAPTCHA_LOGIN', 'true'))), ['false', '0', 'off', 'no'], true);
    }

    /** ساخت کد جدید، ذخیره‌ی هش در نشست و ارسال تصویر PNG */
    public static function output(): void
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        Session::put(self::KEY, ['h' => self::hash($code), 'at' => time()]);
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        imagepng(self::image($code));
        exit;
    }

    /** بررسی پاسخ کاربر؛ کد در هر حال (درست یا غلط) باطل می‌شود تا قابل حدس زدن تکراری نباشد. */
    public static function verify(string $answer): bool
    {
        $c = Session::pull(self::KEY);
        if (!is_array($c) || empty($c['h']) || time() - (int) ($c['at'] ?? 0) > self::EXPIRE) {
            return false;
        }
        $answer = trim($answer);
        return strlen($answer) === self::LENGTH && hash_equals((string) $c['h'], self::hash($answer));
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) env('APP_KEY', 'taskam') . '|captcha');
    }

    /** @return \GdImage|resource */
    private static function image(string $code)
    {
        $w = 170;
        $h = 52;
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        // نقطه‌ها و خط‌های مزاحم پس‌زمینه
        for ($i = 0; $i < 260; $i++) {
            $c = imagecolorallocate($im, random_int(150, 220), random_int(170, 225), random_int(200, 240));
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $c);
        }
        for ($i = 0; $i < 5; $i++) {
            $c = imagecolorallocate($im, random_int(120, 190), random_int(150, 200), random_int(190, 235));
            imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        // هر نویسه: با فونت داخلی کشیده، بزرگ و کمی چرخانده می‌شود
        $font = 5;
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $step = ($w - 20) / self::LENGTH;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $tile = imagecreatetruecolor($fw + 2, $fh + 2);
            $white = imagecolorallocate($tile, 255, 255, 255);
            imagefill($tile, 0, 0, $white);
            $ink = imagecolorallocate($tile, random_int(10, 50), random_int(50, 100), random_int(110, 170));
            imagestring($tile, $font, 1, 1, $code[$i], $ink);
            $sw = (int) round(($fw + 2) * random_int(270, 310) / 100);
            $sh = (int) round(($fh + 2) * random_int(250, 285) / 100);
            $big = imagecreatetruecolor($sw, $sh);
            imagecopyresampled($big, $tile, 0, 0, 0, 0, $sw, $sh, $fw + 2, $fh + 2);
            $rot = imagerotate($big, random_int(-18, 18), imagecolorallocate($big, 255, 255, 255));
            $rw = imagesx($rot);
            $rh = imagesy($rot);
            // کپی با «تیره‌تر شدن» تا پس‌زمینه‌ی سفید کاشی روی نویز نیفتد
            imagelayereffect($im, IMG_EFFECT_NORMAL);
            $x0 = (int) (10 + $i * $step + ($step - $rw) / 2 + random_int(-2, 2));
            $y0 = (int) (($h - $rh) / 2 + random_int(-3, 3));
            for ($x = 0; $x < $rw; $x++) {
                for ($y = 0; $y < $rh; $y++) {
                    $px = imagecolorat($rot, $x, $y);
                    $r = ($px >> 16) & 255;
                    if ($r < 200 && $x0 + $x >= 0 && $x0 + $x < $w && $y0 + $y >= 0 && $y0 + $y < $h) {
                        imagesetpixel($im, $x0 + $x, $y0 + $y, $px);
                    }
                }
            }
            imagedestroy($tile);
            imagedestroy($big);
            imagedestroy($rot);
        }
        // چند خط روی حروف
        for ($i = 0; $i < 2; $i++) {
            $c = imagecolorallocate($im, random_int(60, 120), random_int(90, 140), random_int(150, 200));
            imageline($im, 0, random_int(10, $h - 10), $w, random_int(10, $h - 10), $c);
        }
        return $im;
    }
}
