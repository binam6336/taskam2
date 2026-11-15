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
        // «آخرین بازدید» (پنل مدیر ← جزئیات کاربر): فقط فعالیت خود کاربر، حداکثر هر ۵ دقیقه یک بار
        if (!Auth::isImpersonating() && (empty($u['last_active_at']) || strtotime((string) $u['last_active_at']) < time() - 300)) {
            try {
                \App\Core\DB::run('UPDATE users SET last_active_at = NOW() WHERE id = ?', [(int) $u['id']]);
            } catch (\Throwable $e) {
                // ستون هنوز ساخته نشده (قبل از مهاجرت)
            }
        }
        // v7.8: حساب غیرفعال = فقط خواندنی (دیدن همه‌چیز، هیچ ثبت و تغییری)؛ مسدود و در انتظار تأیید → صفحه‌ی وضعیت حساب
        if ($u['status'] === 'deactivated' && !Auth::isImpersonating()) {
            if (!$r->isSafe() && !in_array($r->path(), Auth::READ_ONLY_ALLOWED, true)) {
                if ($r->wantsJson()) {
                    throw new HttpException(403, Auth::INACTIVE_MSG, 'ACCOUNT_INACTIVE');
                }
                \App\Core\Session::flash(Auth::INACTIVE_MSG, 'error');
                Response::redirect('/dashboard');
            }
            return;
        }
        if ($u['status'] !== 'active') {
            if ($r->wantsJson()) {
                throw new HttpException(403, 'حساب کاربری شما فعال نیست.', 'ACCOUNT_INACTIVE');
            }
            Response::redirect('/pending');
        }
    }
}
