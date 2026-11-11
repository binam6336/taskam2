<?php
declare(strict_types=1);

namespace App\Core;

/** آپلود امن: whitelist پسوند + بررسی MIME واقعی (finfo) + نام تصادفی + ذخیره خارج از webroot. */
final class Uploader
{
    public const ATTACH_MAX = 10 * 1024 * 1024;

    /** پسوند → MIME های مجاز (finfo) */
    public const ATTACH_TYPES = [
        'pdf'  => ['application/pdf'],
        'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'txt'  => ['text/plain'],
        'rtf'  => ['text/rtf', 'application/rtf', 'text/plain'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'rar'  => ['application/x-rar', 'application/x-rar-compressed', 'application/vnd.rar'],
        '7z'   => ['application/x-7z-compressed'],
    ];

    public const CHAT_TYPES = [
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'pdf' => ['application/pdf'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
        'txt' => ['text/plain'],
    ];

    public const SERVE_MIME = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
    ];

    public static function dir(string $type): string
    {
        if (!in_array($type, ['avatars', 'projects', 'task_attachments', 'note_attachments', 'chat_files', 'support_files'], true)) {
            throw new \InvalidArgumentException('bad upload type');
        }
        $d = STORAGE_PATH . '/uploads/' . $type;
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function path(string $type, string $name): ?string
    {
        $name = basename($name);
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $name)) {
            return null;
        }
        $p = self::dir($type) . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function delete(string $type, ?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }
        $p = self::path($type, $name);
        if ($p !== null) {
            @unlink($p);
        }
    }

    /** تبدیل ساختار $_FILES['x'] (چندفایلی) به لیست فایل‌های تکی. */
    public static function normalize(?array $f): array
    {
        if ($f === null || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $n) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $n, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i], 'size' => $f['size'][$i] ?? 0];
        }
        return $out;
    }

    private static function mime(string $tmp): string
    {
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $fi->file($tmp);
    }

    /**
     * ذخیره‌ی یک فایل پیوست.
     * @return array{ok:bool,error?:string,name?:string,original?:string,size?:int,ext?:string,mime?:string}
     */
    /** سهمیه‌ی روزانه‌ی آپلود هر کاربر (مگابایت، همه‌ی بخش‌ها روی هم: چت، تسک، یادداشت، تیکت) */
    public static function dailyQuota(): int
    {
        return max(10, (int) env('UPLOAD_DAILY_MB', 500)) * 1024 * 1024;
    }

    /** حجم آپلودهای ۲۴ ساعت اخیر کاربر */
    public static function usedToday(int $uid): int
    {
        $since = date('Y-m-d H:i:s', time() - 86400);
        return (int) DB::value('SELECT
              (SELECT COALESCE(SUM(attachment_size), 0) FROM chat_messages WHERE sender_id = ? AND created_at > ?)
            + (SELECT COALESCE(SUM(file_size), 0) FROM task_attachments WHERE user_id = ? AND created_at > ?)
            + (SELECT COALESCE(SUM(file_size), 0) FROM note_attachments WHERE user_id = ? AND created_at > ?)
            + (SELECT COALESCE(SUM(attachment_size), 0) FROM support_messages WHERE sender_id = ? AND created_at > ?)', [$uid, $since, $uid, $since, $uid, $since, $uid, $since]);
    }

    /**
     * @param int|null $uid با شناسه‌ی کاربر: سهمیه‌ی حجم روزانه و حداکثر ۶۰ فایل در ساعت هم بررسی می‌شود (جلوی پر کردن دیسک)
     */
    public static function saveAttachment(array $file, string $type, string $prefix, array $types = self::ATTACH_TYPES, int $max = self::ATTACH_MAX, ?int $uid = null): array
    {
        $orig = mb_substr(basename((string) $file['name']), 0, 200, 'UTF-8');
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (خطا در آپلود)')];
        }
        if ($uid !== null) {
            if (!Throttle::attempt('upload', (string) $uid, 60, 3600)) {
                return ['ok' => false, 'error' => ErrorText::apply($orig . ' (تعداد آپلودها زیاد است؛ کمی بعد دوباره تلاش کنید)')];
            }
            if (self::usedToday($uid) + (int) $file['size'] > self::dailyQuota()) {
                return ['ok' => false, 'error' => ErrorText::apply($orig . ' (سهمیه‌ی آپلود امروز شما تمام شده است)')];
            }
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $max) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (حجم نامعتبر؛ حداکثر ' . human_size($max) . ')')];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (آپلود نامعتبر)')];
        }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset($types[$ext])) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (فرمت مجاز نیست)')];
        }
        $mime = self::mime((string) $file['tmp_name']);
        if (!in_array($mime, $types[$ext], true)) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (محتوای فایل با پسوند مطابقت ندارد)')];
        }
        // آرشیوها: docx/xlsx باید ساختار واقعی OOXML داشته باشند (نه هر zip با پسوند عوض‌شده)، بدون ماکرو؛ zip بمب نباشد
        if (in_array($ext, ['docx', 'xlsx', 'zip'], true) && ($why = self::archiveProblem((string) $file['tmp_name'], $ext)) !== null) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (' . $why . ')')];
        }
        if (($why = self::virusScan((string) $file['tmp_name'])) !== null) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (' . $why . ')')];
        }
        $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = self::dir($type) . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => ErrorText::apply($orig . ' (خطا در ذخیره)')];
        }
        @chmod($dest, 0640);
        // تصویرهای پیوست دوباره ساخته می‌شوند تا EXIF (مثل موقعیت GPS) و داده‌ی پنهان حذف شود
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && self::reencode($dest, $ext)) {
            clearstatcache(true, $dest);
            $size = (int) @filesize($dest) ?: $size;
        }
        return ['ok' => true, 'name' => $name, 'original' => $orig, 'size' => $size, 'ext' => $ext, 'mime' => $mime];
    }

    /** حداکثر حجم بازشده و تعداد فایل داخل آرشیو (ضد zip bomb) */
    private const ARCHIVE_MAX_BYTES = 300 * 1024 * 1024;
    private const ARCHIVE_MAX_FILES = 5000;

    /** مشکل آرشیو یا null: ساختار OOXML برای docx/xlsx، ماکرو، zip bomb */
    private static function archiveProblem(string $tmp, string $ext): ?string
    {
        if (!class_exists('ZipArchive')) {
            return in_array($ext, ['docx', 'xlsx'], true) ? 'بررسی فایل روی این سرور ممکن نیست' : null;
        }
        $z = new \ZipArchive();
        if ($z->open($tmp, \ZipArchive::RDONLY) !== true) {
            return 'فایل فشرده‌ی معتبر نیست';
        }
        try {
            if ($z->numFiles > self::ARCHIVE_MAX_FILES) {
                return 'تعداد فایل‌های داخل آرشیو بیش از حد است';
            }
            $total = 0;
            $names = [];
            for ($i = 0; $i < $z->numFiles; $i++) {
                $st = $z->statIndex($i);
                if ($st === false) {
                    return 'فایل فشرده‌ی معتبر نیست';
                }
                $total += (int) $st['size'];
                $names[strtolower((string) $st['name'])] = true;
            }
            if ($total > self::ARCHIVE_MAX_BYTES) {
                return 'حجم بازشده‌ی آرشیو بیش از حد است';
            }
            if ($ext === 'zip') {
                return null;
            }
            $main = $ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            if (!isset($names['[content_types].xml']) || !isset($names[$main])) {
                return 'ساختار فایل ' . strtoupper($ext) . ' معتبر نیست';
            }
            foreach (array_keys($names) as $n) {
                if (substr($n, -15) === 'vbaproject.bin') {
                    return 'فایل دارای ماکرو مجاز نیست';
                }
            }
            return null;
        } finally {
            $z->close();
        }
    }

    /**
     * اسکن ضدبدافزار اختیاری با ClamAV: VIRUS_SCAN=clamdscan (یا clamscan) در .env.
     * خاموش (پیش‌فرض) = بدون اسکن؛ اگر اسکنر در دسترس نباشد آپلود رد نمی‌شود و خطا در لاگ ثبت می‌شود.
     */
    private static function virusScan(string $tmp): ?string
    {
        $bin = strtolower(trim((string) env('VIRUS_SCAN', '')));
        if (!in_array($bin, ['clamdscan', 'clamscan'], true) || !function_exists('exec')) {
            return null;
        }
        $out = [];
        $code = 0;
        @exec($bin . ' --no-summary ' . ($bin === 'clamdscan' ? '--fdpass ' : '') . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        if ($code === 1) {
            return 'فایل آلوده تشخیص داده شد';
        }
        if ($code !== 0) {
            Log::warning('files.virus_scan_failed', ['scanner' => $bin, 'exit' => $code, 'output' => implode(' ', array_slice($out, 0, 3))]);
        }
        return null;
    }

    /** بازسازی تصویر با GD (حذف EXIF/متادیتا)؛ false اگر GD نتواند (فایل همان می‌ماند) */
    private static function reencode(string $path, string $ext): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $data = @file_get_contents($path);
        $im = $data !== false ? @imagecreatefromstring($data) : false;
        if ($im === false) {
            return false;
        }
        $ok = false;
        if ($ext === 'png') {
            imagesavealpha($im, true);
            $ok = @imagepng($im, $path, 6);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            $ok = @imagewebp($im, $path, 90);
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $ok = @imagejpeg($im, $path, 90);
        }
        imagedestroy($im);
        return (bool) $ok;
    }

    /**
     * ذخیره‌ی تصویر پروفایل/پروژه با پردازش مجدد GD (حذف متادیتا و payload های پنهان).
     * @return array{ok:bool,error?:string,name?:string}
     */
/** بیشترین ضلع تصویر پروفایل و لوگوی پروژه (v7.3؛ در پنل حداکثر ۸۴px نمایش داده می‌شود، ۵۱۲ برای صفحه‌های رتینا کافی است) */
    public const IMAGE_MAX_SIDE = 512;

    /** کوچک کردن تصویر GD با حفظ نسبت (اگر بزرگ‌تر از $max باشد) */
    private static function fit($src, int $max, bool $alpha)
    {
        $w = imagesx($src); $h = imagesy($src);
        if ($w <= $max && $h <= $max) {
            return $src;
        }
        $r = min($max / $w, $max / $h);
        $nw = max(1, (int) round($w * $r)); $nh = max(1, (int) round($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        return $dst;
    }

    /**
     * تصویر پروفایل/پروژه‌ی قدیمیِ بزرگ (پیش از v7.3) را یک بار در جا کوچک می‌کند (هنگام اولین نمایش؛ FileController).
     * فقط jpg/png/webp؛ GIF و فایل‌های کوچک دست نمی‌خورند. خطا = همان فایل قبلی.
     */
    public static function shrinkStored(string $path): void
    {
        if (!function_exists('imagecreatetruecolor') || @filesize($path) < 120 * 1024) {
            return;
        }
        $info = @getimagesize($path);
        if ($info === false || ($info[0] <= self::IMAGE_MAX_SIDE && $info[1] <= self::IMAGE_MAX_SIDE)) {
            return;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $src = $ext === 'jpg' ? @imagecreatefromjpeg($path) : ($ext === 'png' ? @imagecreatefrompng($path) : ($ext === 'webp' && function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false));
        if (!$src) {
            return;
        }
        $src = self::fit($src, self::IMAGE_MAX_SIDE, $ext !== 'jpg');
        $tmp = $path . '.tmp';
        $ok = $ext === 'jpg' ? @imagejpeg($src, $tmp, 86) : ($ext === 'png' ? @imagepng($src, $tmp, 8) : @imagewebp($src, $tmp, 86));
        imagedestroy($src);
        if ($ok && @filesize($tmp) > 0) {
            @rename($tmp, $path);
        } else {
            @unlink($tmp);
        }
    }

    public static function saveImage(array $file, string $type, string $prefix, int $maxBytes = 2097152): array
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => ErrorText::apply('خطا در آپلود تصویر.')];
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => ErrorText::apply('حجم تصویر نباید بیشتر از ' . human_size($maxBytes) . ' باشد.')];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => ErrorText::apply('آپلود نامعتبر.')];
        }
        if (!function_exists('imagecreatetruecolor')) {
            return ['ok' => false, 'error' => ErrorText::apply('پردازش تصویر (GD) روی سرور فعال نیست.')];
        }
        $mime = self::mime((string) $file['tmp_name']);
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($map[$mime])) {
            return ['ok' => false, 'error' => ErrorText::apply('فرمت تصویر باید JPG، PNG، GIF یا WebP باشد.')];
        }
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || $info[0] < 10 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 4000 || ($info[0] * $info[1]) > 16000000) {
            return ['ok' => false, 'error' => ErrorText::apply('ابعاد تصویر باید بین ۱۰ و ۴۰۰۰ پیکسل باشد.')];
        }
        if ($mime === 'image/jpeg') {
            $src = @imagecreatefromjpeg((string) $file['tmp_name']);
        } elseif ($mime === 'image/png') {
            $src = @imagecreatefrompng((string) $file['tmp_name']);
        } elseif ($mime === 'image/gif') {
            $src = @imagecreatefromgif((string) $file['tmp_name']);
        } else {
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp((string) $file['tmp_name']) : false;
        }
        if (!$src) {
            return ['ok' => false, 'error' => ErrorText::apply('خطا در پردازش تصویر.')];
        }
        $src = self::fit($src, self::IMAGE_MAX_SIDE, $mime !== 'image/jpeg');   // v7.3: تصویر پروفایل/پروژه حداکثر ۵۱۲px
        $ext = $map[$mime];
        if ($ext === 'webp' && !function_exists('imagewebp')) {
            $ext = 'jpg';
        }
        if ($ext !== 'jpg') {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }
        $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = self::dir($type) . '/' . $name;
        if ($ext === 'jpg') {
            $ok = @imagejpeg($src, $dest, 88);
        } elseif ($ext === 'png') {
            $ok = @imagepng($src, $dest, 8);
        } elseif ($ext === 'gif') {
            $ok = @imagegif($src, $dest);
        } else {
            $ok = @imagewebp($src, $dest, 88);
        }
        imagedestroy($src);
        if (!$ok) {
            return ['ok' => false, 'error' => ErrorText::apply('خطا در ذخیره‌ی تصویر.')];
        }
        @chmod($dest, 0640);
        return ['ok' => true, 'name' => $name];
    }
}
