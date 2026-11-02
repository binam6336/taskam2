<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** هر کاربر واردشده (حتی pending/مسدود) — برای صفحه‌ی وضعیت حساب. */
final class AuthAny
{
    public static function handle(Request $r): void
    {
        if (Auth::user() === null) {
            if (Auth::needsReauth()) {                     // شبکه عوض شده: فقط رمز دوباره (نه ورود کامل)
                if ($r->wantsJson()) {
                    throw new HttpException(401, 'شبکه‌ی اینترنت شما عوض شده است؛ برای ادامه رمز عبور را دوباره وارد کنید.', 'REAUTH_REQUIRED');
                }
                Response::redirect('/reauth');
            }
            if ($r->wantsJson()) {
                throw new HttpException(401, 'نشست شما منقضی شده است.', 'UNAUTHORIZED');
            }
            Response::redirect('/login');
        }
    }
}
