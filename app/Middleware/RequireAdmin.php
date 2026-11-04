<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** فقط ادمین واقعی (نه نشست impersonate‌شده). */
final class RequireAdmin
{
    public static function handle(Request $r): void
    {
        $u = Auth::user();
        if ($u === null) {
            Response::redirect(Auth::needsReauth() ? '/reauth' : '/login');
        }
        // داخل پنجره‌ی ورود به پنل کاربر صفحه‌ی مدیریت باز نمی‌شود؛ GET به آدرس اصلی پنل مدیر (بیرون از پنجره) می‌رود
        if (Auth::isImpersonating()) {
            if ($r->method() === 'GET' && !$r->wantsJson()) {
                Response::redirect(app_base() . '/admin');
            }
            throw new HttpException(403, 'داخل پنجره‌ی پنل کاربر به بخش مدیریت دسترسی نیست.', 'FORBIDDEN');
        }
        if ($u['status'] !== 'active' && $u['role'] === 'admin' && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/pending');                   // مدیر مسدود/غیرفعال‌شده
        }
        if ($u['role'] !== 'admin' || $u['status'] !== 'active' || Auth::isImpersonating()) {
            throw new HttpException(403, 'دسترسی به این بخش مجاز نیست.', 'FORBIDDEN');
        }
    }
}
