<?php
declare(strict_types=1);

namespace App\Core;

/** توکن CSRF مبتنی بر سشن (double-submit از طریق فیلد فرم یا هدر X-CSRF-Token). */
final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32));
            Session::put('_csrf', $t);
        }
        return $t;
    }

    public static function verify(Request $r): bool
    {
        // فقط هدر یا بدنه‌ی POST/JSON — نه query string (توکن در URL از راه لاگ و Referer نشت می‌کند)
        $sent = $r->header('X-CSRF-Token') ?? (string) ($r->post['_csrf'] ?? ($r->json()['_csrf'] ?? ''));
        $real = (string) Session::get('_csrf', '');
        return $real !== '' && $sent !== '' && hash_equals($real, (string) $sent);
    }
}
