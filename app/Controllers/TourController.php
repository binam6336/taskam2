<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/** تور آموزشی پنل: ذخیره‌ی وضعیت (skip / done / شماره‌ی مرحله) تا بین صفحه‌ها ادامه پیدا کند. */
final class TourController extends Controller
{
    public function update()
    {
        $state = $this->request->str('state', 20);
        if (!in_array($state, ['skip', 'done'], true) && !preg_match('/^[1-9]\d?$/', $state)) {
            $this->respond(false, 'وضعیت نامعتبر است.', '/dashboard');
        }
        // در حالت مشاهده‌ی پنل دیگران، وضعیت آموزش خود کاربر دست نمی‌خورد
        if (!Auth::isImpersonating()) {
            DB::update('users', ['tour' => $state], 'id = ?', [$this->uid()]);
        }
        $this->respond(true, '', '/dashboard');
    }
}
