<?php
declare(strict_types=1);

namespace KarlixLaravel\Http\Middleware\Checks;

use KarlixLaravel\Auth\Authentication as Auth;
use App\Core\Request;
use KarlixLaravel\Http\Response;

/** صفحات ورود/ثبت‌نام: کاربر واردشده به خانه هدایت می‌شود. */
final class Guest
{
    public static function handle(Request $r): void
    {
        $u = Auth::user();
        if ($u !== null) {
            Response::redirect('/');
        }
    }
}
