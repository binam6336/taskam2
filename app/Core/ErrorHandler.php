<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** مدیریت متمرکز خطا: لاگ در storage/logs و پاسخ امن (بدون نشت جزئیات). */
final class ErrorHandler
{
    public static function register(): void
    {
        $debug = (bool) env('APP_DEBUG', false);
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

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
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function respond(int $status, string $message, string $code, ?Throwable $debug): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
        }
        $req = Request::capture();
        if ($req->wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $out = ['success' => false, 'status' => 'error', 'message' => $message, 'error_code' => $code];
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
        (static function () use ($view, $status, $message, $detail): void {
            require $view;
        })();
    }
}
