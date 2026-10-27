<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/** توکن CSRF برای تمام درخواست‌های تغییردهنده‌ی وب. */
final class VerifyCsrf
{
    public static function handle(Request $r): void
    {
        if ($r->isSafe()) {
            return;
        }
        if (!Csrf::verify($r)) {
            throw new HttpException(419, 'نشست شما منقضی شده یا درخواست نامعتبر است. صفحه را رفرش کنید.', 'CSRF_MISMATCH');
        }
    }
}
