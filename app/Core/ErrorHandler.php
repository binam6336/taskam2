<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** مدیریت متمرکز خطا: لاگ تفکیک‌شده در storage/logs/<بخش>/ (App\Core\Log) و پاسخ امن (بدون نشت جزئیات). */
final class ErrorHandler
{
    public static function register(): void
    {
        $debug = (bool) env('APP_DEBUG', false);
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        // آرگومان توابع (رمز عبور، رمز DB و …) در trace استثناها و لاگ ذخیره نشود
        ini_set('zend.exception_ignore_args', '1');

        // هشدار/نوتیس/deprecated: در حالت debug استثنا (برای کشف باگ)، در تولید فقط لاگ (پایداری سرویس)
        set_error_handler(static function (int $no, string $msg, string $file, int $line) use ($debug): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            $e = new \ErrorException($msg, 0, $no, $file, $line);
            if ($debug && !in_array($no, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                throw $e;
            }
            self::log($e);
            return true;
        });

        set_exception_handler(static function (Throwable $e) use ($debug): void {
            if ($e instanceof HttpException) {
                self::respond($e->status, $e->getMessage(), $e->errorCode, null);
                return;
            }
            self::log($e);
            self::respond(500, 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.', 'SERVER_ERROR', $debug ? $e : null);
        });
    }

    public static function log(Throwable $e): void
    {
        // ستون/جدول ناموجود (مثلاً فایل نشانه‌ی مهاجرت هست ولی تغییرات در این دیتابیس اعمال نشده): نشانه پاک شود
        // تا درخواست بعدی Schema::ensure دوباره اجرا شود (SCHEMA_AUTO)
        for ($x = $e; $x; $x = $x->getPrevious()) {
            if ($x instanceof \PDOException && in_array((string) $x->getCode(), ['42S22', '42S02'], true)) {
                @unlink(STORAGE_PATH . '/cache/schema-' . Schema::VERSION);
                break;
            }
        }
        // ثبت در storage/logs/<بخش>/YYYY-MM-DD.log + یک خط در لاگ عمومی روز (App\Core\Log)
        Log::error($e);
    }

    private static function respond(int $status, string $message, string $code, ?Throwable $debug): void
    {
        try {
            $message = ErrorText::apply($message);
        } catch (Throwable $e) {
            // متن پیش‌فرض
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
        }
        // خط فرمان (bin/*.php): متن ساده به جای صفحه‌ی HTML
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'خطا: ' . ($debug ? $debug->getMessage() . ' (' . basename($debug->getFile()) . ':' . $debug->getLine() . ')' : $message) . "\n");
            exit(1);
        }
        $req = Request::capture();
        if ($req->wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $out = ['success' => false, 'status' => 'error', 'message' => $message, 'error_code' => $code];
            if ($status >= 500) {
                $out['ref'] = Log::requestId();                // کد پیگیری = شناسه‌ی درخواست در لاگ
            }
            if ($debug) {
                $out['debug'] = $debug->getMessage() . ' @ ' . basename($debug->getFile()) . ':' . $debug->getLine();
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $detail = $debug ? ($debug->getMessage() . ' — ' . basename($debug->getFile()) . ':' . $debug->getLine()) : null;
        $view = APP_PATH . '/Views/errors/error.php';
        $ref = $status >= 500 ? Log::requestId() : null;
        (static function () use ($view, $status, $message, $detail, $ref): void {
            require $view;
        })();
    }
}
