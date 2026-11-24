<?php
declare(strict_types=1);
namespace KarlixLaravel\Session;

/**
 * درخواست هم‌زمان manifest/service-worker ممکن است هنوز کوکی مهمان پیش از ورود را بفرستد.
 * حذف فوری آن ردیف، strict mode را وادار به صدور کوکی مهمان تازه و بازنویسی کوکی ورود می‌کند.
 * فقط برای مهمان، ردیف قبلی تهی و بدون هیچ هویتی باقی می‌ماند؛ شناسهٔ احرازشده کاملاً تازه است.
 * هیچ ارجاعی از شناسهٔ مهمان به نشست واردشده ذخیره نمی‌شود.
 */
final class LoginRotation
{
    public static function rotate(): void
    {
        \App\Core\Session::start();
        if ((int) \App\Core\Session::get('uid', 0) > 0) {
            \App\Core\Session::regenerate();
            return;
        }
        $guest = $_SESSION;
        $_SESSION = [];
        if (!session_regenerate_id(false)) {
            $_SESSION = $guest;
            throw new \RuntimeException('Could not rotate the login session');
        }
        $_SESSION = $guest;
        $_SESSION['_rot'] = time();
        unset($_SESSION['_destroyed']);
    }
}
