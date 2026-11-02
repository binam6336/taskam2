<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** پنل کاربری: کاربر فعال؛ ادمین (بدون impersonate) به پنل مدیریت هدایت می‌شود. */
final class UserPanel
{
    /** سقف کلی POST پنل وب در دقیقه (در Authenticate اعمال می‌شود؛ گروه‌های auth و panel) */
    public const WRITE_PER_MIN = 120;

    public static function handle(Request $r): void
    {
        Authenticate::handle($r);
        if (Auth::isAdmin() && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/admin');
        }
    }
}
