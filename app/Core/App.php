<?php
declare(strict_types=1);

namespace App\Core;

/** Front controller: هدرهای امنیتی، سشن، بارگذاری مسیرها و dispatch. */
final class App
{
    public static function run(): void
    {
        $req = Request::capture();
        self::securityHeaders();
        // SCHEMA_AUTO=false: مهاجرت خودکار در درخواست وب خاموش است (کاربر DB برنامه بدون ALTER/CREATE؛ مهاجرت با php bin/migrate.php)
        if (filter_var(env('SCHEMA_AUTO', true), FILTER_VALIDATE_BOOLEAN)) {
            Schema::ensure();
        }
        // نشست فقط اگر مرورگر کوکی نشست دارد از ابتدا باز می‌شود؛ برای مهمان در اولین نوشتن (توکن CSRF فرم‌ها، پیام فلش) ساخته می‌شود.
        // درخواست‌های API با توکن Bearer اصلاً نشست ندارند. (جلوی ساخت یک فایل نشست به ازای هر درخواست ناشناس را می‌گیرد)
        if (!($req->isApi() && $req->bearerToken() !== null) && Session::hasCookie()) {
            Session::start();
        }
        // یادآوری تسک‌ها برای اعلان PWA: حداکثر دقیقه‌ای یک‌بار، بعد از پاسخ (جایگزین کران‌جاب روی هاست اشتراکی)
        register_shutdown_function(static function (): void {
            try {
                \App\Services\Push::sweepDue();
                \App\Services\Push::flush();
            } catch (\Throwable $e) {
                ErrorHandler::log($e);
            }
        });
        $router = new Router();
        require BASE_PATH . '/config/routes.php';
        $router->dispatch($req);
    }

    private static function securityHeaders(): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
            . "font-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; "
            . "img-src 'self' data: blob:; connect-src 'self'; media-src 'self'; object-src 'none'; "
            . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; worker-src 'self'; manifest-src 'self'");
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (env('TRUSTED_PROXY', false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
