<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Throttle;

/** پنل کاربری: کاربر فعال؛ ادمین (بدون impersonate) به پنل مدیریت هدایت می‌شود. */
final class UserPanel
{
    public const WRITE_PER_MIN = 120;

    public static function handle(Request $r): void
    {
        Authenticate::handle($r);
        if (Auth::isAdmin() && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/admin');
        }
        // سقف کلی درخواست‌های تغییر‌دهنده‌ی پنل وب برای هر کاربر (مثل سقف عمومی API)؛ جلوی اسکریپت‌های پرحجم
        if ($r->method() === 'POST' && !Throttle::attempt('web_write', (string) Auth::id(), self::WRITE_PER_MIN, 60)) {
            header('Retry-After: 60');
            throw new HttpException(429, 'درخواست‌ها بیش از حد سریع است؛ چند لحظه بعد دوباره تلاش کنید.', 'RATE_LIMITED');
        }
    }
}
