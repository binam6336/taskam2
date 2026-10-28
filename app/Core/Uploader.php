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
    public static function saveAttachment(array $file, string $type, string $prefix, array $types = self::ATTACH_TYPES, int $max = self::ATTACH_MAX): array
    {
        $orig = mb_substr(basename((string) $file['name']), 0, 200, 'UTF-8');
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $orig . ' (خطا در آپلود)'];
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $max) {
            return ['ok' => false, 'error' => $orig . ' (حجم نامعتبر؛ حداکثر ' . human_size($max) . ')'];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => $orig . ' (آپلود نامعتبر)'];
        }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset($types[$ext])) {
            return ['ok' => false, 'error' => $orig . ' (فرمت مجاز نیست)'];
        }
        $mime = self::mime((string) $file['tmp_name']);
        if (!in_array($mime, $types[$ext], true)) {
            return ['ok' => false, 'error' => $orig . ' (محتوای فایل با پسوند مطابقت ندارد)'];
        }
        $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = self::dir($type) . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => $orig . ' (خطا در ذخیره)'];
        }
        @chmod($dest, 0640);
        return ['ok' => true, 'name' => $name, 'original' => $orig, 'size' => $size, 'ext' => $ext, 'mime' => $mime];
    }

    /**
     * ذخیره‌ی تصویر پروفایل/پروژه با پردازش مجدد GD (حذف متادیتا و payload های پنهان).
     * @return array{ok:bool,error?:string,name?:string}
     */
    public static function saveImage(array $file, string $type, string $prefix, int $maxBytes = 2097152): array
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'خطا در آپلود تصویر.'];
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'حجم تصویر نباید بیشتر از ' . human_size($maxBytes) . ' باشد.'];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'آپلود نامعتبر.'];
        }
        if (!function_exists('imagecreatetruecolor')) {
            return ['ok' => false, 'error' => 'پردازش تصویر (GD) روی سرور فعال نیست.'];
        }
        $mime = self::mime((string) $file['tmp_name']);
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($map[$mime])) {
            return ['ok' => false, 'error' => 'فرمت تصویر باید JPG، PNG، GIF یا WebP باشد.'];
        }
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || $info[0] < 10 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 4000 || ($info[0] * $info[1]) > 16000000) {
            return ['ok' => false, 'error' => 'ابعاد تصویر باید بین ۱۰ و ۴۰۰۰ پیکسل باشد.'];
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
            return ['ok' => false, 'error' => 'خطا در پردازش تصویر.'];
        }
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
            return ['ok' => false, 'error' => 'خطا در ذخیره‌ی تصویر.'];
        }
        @chmod($dest, 0640);
        return ['ok' => true, 'name' => $name];
    }
}
