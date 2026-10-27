<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** کاربر واردشده با وضعیت active (یا ادمین). */
final class Authenticate
{
    public static function handle(Request $r): void
    {
        $u = Auth::user();
        if ($u === null) {
            if ($r->wantsJson()) {
                throw new HttpException(401, 'نشست شما منقضی شده است. دوباره وارد شوید.', 'UNAUTHORIZED');
            }
            Response::redirect('/login');
        }
        if ($u['status'] !== 'active' && $u['role'] !== 'admin') {
            if ($r->wantsJson()) {
                throw new HttpException(403, 'حساب کاربری شما فعال نیست.', 'ACCOUNT_INACTIVE');
            }
            Response::redirect('/pending');
        }
    }
}
