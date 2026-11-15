<?php
declare(strict_types=1);

namespace App\Core;

/** ساخت پاسخ‌ها. هر متد بعد از ارسال، اجرا را پایان می‌دهد. */
final class Response
{
    public static function json(array $data, int $status = 200)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        // متن خطاهایی که ادمین در «محتوا ← خطاها» ویرایش کرده
        if (($data['status'] ?? null) === 'error' || ($data['success'] ?? null) === false) {
            foreach (['message', 'error'] as $k) {
                if (isset($data[$k]) && is_string($data[$k])) {
                    $data[$k] = ErrorText::apply($data[$k]);
                }
            }
        }
        // شناسه‌ی عددی دیتابیس بیرون نرود (کلیدهای *_id → pid؛ Pid::OUTPUTS)
        try {
            $data = Pid::out($data);
        } catch (\Throwable $e) {
            // اگر ستون pid هنوز ساخته نشده (مهاجرت ناقص)، خروجی خراب نشود
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        self::emit((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /** آیا PHP می‌تواند پاسخ را پیش از کارهای بعد از پاسخ (ارسال push، یادآوری‌ها) ببندد؟ */
    public static function canFinish(): bool
    {
        return PHP_SAPI !== 'cli' && (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'));
    }

    /** بستن پاسخ برای مرورگر؛ کارهای بعدی (push) بدون معطل کردن کاربر ادامه پیدا می‌کنند */
    public static function finish(): void
    {
        // قفل نشست (GET_LOCK در DbSessionHandler) همین‌جا آزاد شود؛ وگرنه درخواست‌های بعدی همین کاربر
        // (تازه‌سازی لیست، جزئیات تسک، poll) تا پایان ارسال push پشت قفل می‌ماندند (v5.13.1)
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        } else {
            @ignore_user_abort(true);
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }
    }

    /**
     * ارسال بدنه با Content-Length (v5.13.1): مرورگر به‌محض رسیدن همه‌ی بایت‌ها پاسخ را تمام‌شده می‌داند،
     * حتی اگر PHP بعد از آن هنوز push می‌فرستد. روی سرورهای بدون fastcgi/litespeed_finish_request اتصال بسته
     * و (فقط وقتی کار بعد از پاسخ در صف است) فشرده‌سازی خاموش می‌شود تا طول پاسخ دست نخورد.
     */
    private static function emit(string $body): void
    {
        // بعد از ساختن پاسخ دیگر چیزی در نشست نوشته نمی‌شود؛ قفل نشست پیش از ارسال بدنه آزاد می‌شود
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (!headers_sent()) {
            if (!self::canFinish()) {
                header('Connection: close');
                if (\App\Services\Push::pending()) {
                    header('Content-Encoding: none');
                }
            }
            header('Content-Length: ' . strlen($body));
        }
        echo $body;
        exit;
    }

    public static function redirect(string $to, int $status = 302)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        // مسیر protocol-relative (//evil.com) یا با بک‌اسلش (/\evil.com) مقصد بیرونی است؛ فقط مسیر داخلی /x یا آدرس کامل همین سایت
        if (preg_match('#^\s*[/\\\\]{2}#', $to) || preg_match('#^/[\\\\]#', $to) || preg_match('#[\r\n]#', $to)) {
            $to = '/';
        }
        if (!preg_match('#^https?://#i', $to)) {
            // داخل پنجره‌ی ورود به پنل کاربر، صفحه‌های مدیر/ورود بیرون از پنجره (نشست اصلی) باز می‌شوند
            if (Impersonation::active() && preg_match('#^/(admin|login|reauth|register|pending)(/|\?|$)#', $to)) {
                $to = app_base() . $to;
            } elseif (!(base_url() !== '' && (str_starts_with($to, base_url() . '/') || $to === base_url()))) {
                $to = url($to);
            }
        } else {
            // جلوگیری از open-redirect: فقط همان origin
            $host = parse_url($to, PHP_URL_HOST);
            if ($host !== ($_SERVER['HTTP_HOST'] ?? null) && $host !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
                $to = url('/');
            }
        }
        http_response_code($status);
        header('Location: ' . $to);
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();                 // فلش پیش از این ثبت شده؛ صفحه‌ی مقصد پشت قفل نشست نماند (v5.13.1)
        }
        header('Content-Length: 0');
        exit;
    }

    public static function html(string $html, int $status = 200)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        self::emit($html);
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }
}
