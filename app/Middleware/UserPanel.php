<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** پنل کاربری: کاربر فعال؛ ادمین (بدون impersonate) به پنل مدیریت هدایت می‌شود. */
final class UserPanel
{
    public static function handle(Request $r): void
    {
        Authenticate::handle($r);
        if (Auth::isAdmin() && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/admin');
        }
    }
}
