<?php
declare(strict_types=1);

namespace KarlixLaravel\Http\Middleware\Checks;

use KarlixLaravel\Auth\Authentication as Auth;
use App\Core\Request;
use KarlixLaravel\Http\Response;

/** پنل کاربری: کاربر فعال؛ ادمین (بدون impersonate) به پنل مدیریت هدایت می‌شود. */
final class UserPanel
{
    /** سقف کلی POST پنل وب در دقیقه (در Authenticate اعمال می‌شود؛ گروه‌های auth و panel) */
    public const WRITE_PER_MIN = 120;

    public static function handle(Request $r): void
    {
        Authenticate::handle($r);
        \KarlixLaravel\Services\TaskRecurrence::warm();
        if (Auth::isAdmin() && !Auth::isImpersonating() && !$r->wantsJson()) {
            Response::redirect('/admin');
        }
    }
}
