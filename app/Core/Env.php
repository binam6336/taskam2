<?php
declare(strict_types=1);

namespace App\Core;

/** خواندن فایل .env (بدون وابستگی خارجی). */
final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
                $q = $v[0];
                $end = strrpos($v, $q);
                $v = $end > 0 ? substr($v, 1, $end - 1) : substr($v, 1);
            } else {
                $v = $v !== '' && $v[0] === '#' ? '' : trim((string) preg_replace('/\s+#.*$/', '', $v));   // مقدار خالی + توضیح
            }
            self::$vars[$k] = $v;
        }
    }

    public static function get(string $key, $default = null)
    {
        $v = self::$vars[$key] ?? ($_ENV[$key] ?? getenv($key));
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        $l = strtolower((string) $v);
        if ($l === 'true') {
            return true;
        }
        if ($l === 'false') {
            return false;
        }
        if ($l === 'null') {
            return null;
        }
        return $v;
    }
}
