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
            Response::redirect('/login');
        }
        // اگر مدیر در حال مشاهده‌ی پنل کاربر است و صفحه‌ی مدیریت را باز می‌کند، به‌جای ۴۰۳ به نشست مدیر برمی‌گردد
        if (Auth::isImpersonating() && $r->method() === 'GET' && !$r->wantsJson()) {
            Auth::stopImpersonation();
            $u = Auth::user();
        }
        if ($u['status'] !== 'active' && $u['role'] === 'admin' && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/pending');                   // مدیر مسدود/غیرفعال‌شده
        }
        if ($u['role'] !== 'admin' || $u['status'] !== 'active' || Auth::isImpersonating()) {
            throw new HttpException(403, 'دسترسی به این بخش مجاز نیست.', 'FORBIDDEN');
        }
    }
}
