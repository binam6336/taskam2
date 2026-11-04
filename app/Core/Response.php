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
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
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
            $to = str_starts_with($to, base_url() . '/') || $to === base_url() ? $to : url($to);
        } else {
            // جلوگیری از open-redirect: فقط همان origin
            $host = parse_url($to, PHP_URL_HOST);
            if ($host !== ($_SERVER['HTTP_HOST'] ?? null) && $host !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
                $to = url('/');
            }
        }
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    public static function html(string $html, int $status = 200)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }
}
