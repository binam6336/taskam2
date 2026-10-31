<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Throttle;

/**
 * احراز هویت API:
 *  1) Bearer / X-API-Key  (توکن ذخیره‌شده به‌صورت SHA-256)
 *  2) نشست مرورگر (کوکی) — در این حالت برای درخواست‌های تغییردهنده، هدر X-CSRF-Token الزامی است.
 * کاربر احراز‌شده در Request::$params['_api_user'] قرار می‌گیرد.
 */
final class ApiAuth
{
    public static function cors(): void
    {
        // CORS فقط برای دامنه‌های تعریف‌شده در API_CORS_ORIGIN (چند دامنه با کاما؛ «*» فقط اگر صریحاً تنظیم شود). خالی = بدون CORS
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) env('API_CORS_ORIGIN', '')))));
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && in_array(rtrim($origin, '/'), array_map(static fn($o) => rtrim($o, '/'), $allowed), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type, Accept, X-CSRF-Token');
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    public static function handle(Request $r): void
    {
        self::cors();
        if ($r->method() === 'OPTIONS') {
            Response::noContent();
        }

        $token = $r->bearerToken();
        if ($token !== null) {
            // حدس زدن توکن: بعد از ۲۰ توکن نامعتبر در ۱۰ دقیقه، آن IP موقتاً مسدود می‌شود
            $ip = $r->ip();
            if (Throttle::count('api_bad_token', $ip, 600) >= 20) {
                header('Retry-After: 600');
                throw new HttpException(429, 'تعداد درخواست‌های نامعتبر زیاد است؛ چند دقیقه بعد تلاش کنید.', 'RATE_LIMITED');
            }
            if (strlen($token) < 20 || strlen($token) > 128) {
                Throttle::hit('api_bad_token', $ip);
                throw new HttpException(401, 'فرمت توکن نامعتبر است.', 'TOKEN_INVALID_FORMAT');
            }
            $row = DB::one(
                'SELECT t.id AS token_id, t.user_id, t.expires_at, t.scope, u.status
                   FROM api_tokens t INNER JOIN users u ON u.id = t.user_id
                  WHERE t.token_hash = ? AND t.is_active = 1 AND u.is_bot = 0 LIMIT 1',
                [hash('sha256', $token)]
            );
            if ($row === null || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time())) {
                Throttle::hit('api_bad_token', $ip);
                throw new HttpException(401, 'توکن نامعتبر یا غیرفعال است.', 'TOKEN_INVALID');
            }
            if ($row['status'] !== 'active') {
                throw new HttpException(403, 'حساب کاربری فعال نیست.', 'ACCOUNT_INACTIVE');
            }
            if (($row['scope'] ?? 'write') === 'read' && !$r->isSafe()) {
                throw new HttpException(403, 'این توکن فقط خواندنی است و اجازه‌ی تغییر داده ندارد.', 'TOKEN_READ_ONLY');
            }
            DB::run('UPDATE api_tokens SET last_used_at = NOW(), last_ip = ? WHERE id = ?', [$r->ip(), (int) $row['token_id']]);
            self::limit((int) $row['user_id']);
            $r->params['_api_user'] = (int) $row['user_id'];
            $r->params['_api_method'] = 'token';
            return;
        }

        $u = Auth::user();
        if ($u === null) {
            throw new HttpException(401, 'دسترسی غیرمجاز. توکن ارسال کنید یا وارد شوید.', 'UNAUTHORIZED');
        }
        if ($u['status'] !== 'active') {
            throw new HttpException(403, 'حساب کاربری فعال نیست.', 'ACCOUNT_INACTIVE');
        }
        if (!$r->isSafe() && !Csrf::verify($r)) {
            throw new HttpException(419, 'توکن CSRF نامعتبر است (برای نشست مرورگر هدر X-CSRF-Token لازم است).', 'CSRF_MISMATCH');
        }
        self::limit((int) $u['id']);
        $r->params['_api_user'] = (int) $u['id'];
        $r->params['_api_method'] = 'session';
    }

    /** سقف درخواست هر کاربر: API_RATE_LIMIT در دقیقه (پیش‌فرض ۱۲۰؛ ۰ = بدون سقف). */
    private static function limit(int $uid): void
    {
        $max = (int) env('API_RATE_LIMIT', 120);
        if ($max <= 0) {
            return;
        }
        if (!Throttle::attempt('api_user', (string) $uid, $max, 60)) {
            header('Retry-After: 60');
            throw new HttpException(429, 'تعداد درخواست‌ها از سقف مجاز (' . $max . ' در دقیقه) بیشتر شده است؛ کمی بعد تلاش کنید.', 'RATE_LIMITED');
        }
    }
}
