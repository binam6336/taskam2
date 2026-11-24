<?php
declare(strict_types=1);

namespace KarlixLaravel\Http;
use App\Core\Impersonation;
use App\Core\Pid;
use App\Core\ErrorText;

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
    public static function canFinish(): bool { return false; }

    /** بستن پاسخ برای مرورگر؛ کارهای بعدی (push) بدون معطل کردن کاربر ادامه پیدا می‌کنند */
    public static function finish(): void
    {
        self::saveSession();
    }

    /**
     * ارسال بدنه با Content-Length (v5.13.1): مرورگر به‌محض رسیدن همه‌ی بایت‌ها پاسخ را تمام‌شده می‌داند،
     * حتی اگر PHP بعد از آن هنوز push می‌فرستد. روی سرورهای بدون fastcgi/litespeed_finish_request اتصال بسته
     * و (فقط وقتی کار بعد از پاسخ در صف است) فشرده‌سازی خاموش می‌شود تا طول پاسخ دست نخورد.
     */
    private static function emit(string $body): void
    {
        self::saveSession();
        $headers = self::headers();
        $response = new \Illuminate\Http\Response($body, http_response_code() ?: 200, $headers);
        if (!isset($headers['Cache-Control'])) { $response->headers->remove('Cache-Control'); }
        throw new CompletedResponse($response);
    }

    /** پیش از آزاد شدن قفل PHP، flashهای Laravel نیز در سطل مشترک ثبت شوند. */
    private static function saveSession(): void
    {
        $app = \Illuminate\Container\Container::getInstance();
        if ($app->bound('session.store') && $app->resolved('session.store')) {
            $app->make('session.store')->save();
        }
        \App\Core\Session::release();
    }

    public static function headers(): array
    {
        $out = [];
        foreach (headers_list() as $header) {
            if (strpos($header, ':') === false) { continue; }
            [$name, $value] = explode(':', $header, 2);
            if (in_array(strtolower($name), ['set-cookie','content-length','connection'], true)) { continue; }
            $out[$name] = trim($value);
        }
        return $out;
    }

    public static function download(string $path): void
    {
        self::saveSession();
        throw new CompletedResponse(new \Symfony\Component\HttpFoundation\StreamedResponse(static function () use ($path) { readfile($path); }, http_response_code() ?: 200, self::headers()));
    }
    public static function complete(string $body = ''): void { self::emit($body); }

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
            if (self::origin($to) !== self::origin(full_url('/'))) {
                $to = url('/');
            }
        }
        http_response_code($status);
        header('Location: ' . $to);
        self::saveSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();                 // فلش پیش از این ثبت شده؛ صفحه‌ی مقصد پشت قفل نشست نماند (v5.13.1)
        }
        header('Content-Length: 0');
        self::emit('');
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || isset($parts['user']) || isset($parts['pass'])) { return ''; }
        $scheme = strtolower($parts['scheme'] ?? '');
        return $scheme . '://' . strtolower($parts['host'] ?? '') . ':' . ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
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
        header('Content-Type: text/html; charset=UTF-8');
        self::emit('');
    }
}
