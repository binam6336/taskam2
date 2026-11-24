<?php
declare(strict_types=1);
namespace KarlixLaravel;

use App\Core\Router as LegacyRouter;
use App\Core\Request as LegacyRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Route;

/** مرحله انتقال: مسیریابی Laravel با حفظ کنترلر و قرارداد نشست/CSRF فعلی. مهاجرت منطق تجاری نیست. */
final class Bridge
{
    public static function dispatch(LegacyRouter $legacy, LegacyRequest $request): void
    {
        $app = Application::configure(basePath: dirname(__DIR__))
            ->withRouting(using: static function () use ($legacy, $request): void {
                Route::options('/{any?}', static function () use ($legacy, $request) { $legacy->dispatch($request); })->where('any', '.*');
                foreach ($legacy->definitions() as $route) {
                    if (str_starts_with($route['path'], '/admin')) { continue; }
                    Route::match([$route['method']], $route['path'], static function () use ($legacy, $request) {
                        // روتر قدیمی همچنان مرجع PID، دسترسی، CSRF و پاسخ است؛ هیچ مسیر مستقیم کنترلر ایجاد نمی‌شود.
                        $legacy->dispatch($request);
                        return response('', 204);
                    });
                }
            })
            ->withMiddleware(static function (Middleware $middleware): void { $middleware->use([]); })
            ->withExceptions(static function (Exceptions $exceptions) use ($legacy, $request): void {
                $exceptions->render(static function (\Throwable $error) use ($legacy, $request) {
                    if ($error instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException || $error instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) { $legacy->dispatch($request); }
                    throw $error;
                });
            })
            ->create();
        // پارامترهای اصلی فقط در LegacyRequest باقی می‌مانند؛ تبدیل خودکار ورودی Laravel روی قرارداد موجود اعمال نمی‌شود.
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();
        $kernel->setGlobalMiddleware([]);
        date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Tehran'));
        \App\Core\ErrorHandler::register();
        $incoming = \Illuminate\Http\Request::create($request->path(), $request->method());
        $response = $kernel->handle($incoming);
        $response->send();
        $kernel->terminate($incoming, $response);
    }
}
