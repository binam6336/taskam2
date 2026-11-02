<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Throttle;

/** کاربر واردشده با وضعیت active (مدیر مسدود/غیرفعال هم استثنا ندارد). */
final class Authenticate
{
    public static function handle(Request $r): void
    {
        $u = Auth::user();
        if ($u === null) {
            if (Auth::needsReauth()) {                     // شبکه عوض شده: فقط رمز دوباره (نه ورود کامل)
                if ($r->wantsJson()) {
                    throw new HttpException(401, 'شبکه‌ی اینترنت شما عوض شده است؛ برای ادامه رمز عبور را دوباره وارد کنید.', 'REAUTH_REQUIRED');
                }
                Response::redirect('/reauth');
            }
            if ($r->wantsJson()) {
                throw new HttpException(401, 'نشست شما منقضی شده است. دوباره وارد شوید.', 'UNAUTHORIZED');
            }
            Response::redirect('/login');
        }
        // سقف کلی درخواست‌های تغییر‌دهنده‌ی پنل وب برای هر کاربر (گروه‌های auth و panel؛ API سقف خودش را دارد)
        if ($r->method() === 'POST' && !Throttle::attempt('web_write', (string) $u['id'], UserPanel::WRITE_PER_MIN, 60)) {
            header('Retry-After: 60');
            throw new HttpException(429, 'درخواست‌ها بیش از حد سریع است؛ چند لحظه بعد دوباره تلاش کنید.', 'RATE_LIMITED');
        }
        if ($u['status'] !== 'active') {
            if ($r->wantsJson()) {
                throw new HttpException(403, 'حساب کاربری شما فعال نیست.', 'ACCOUNT_INACTIVE');
            }
            Response::redirect('/pending');
        }
    }
}
