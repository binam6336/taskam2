<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

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
