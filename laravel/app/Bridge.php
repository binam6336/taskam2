<?php
declare(strict_types=1);

namespace KarlixLaravel;

use App\Core\Request;
use App\Core\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Route;
use KarlixLaravel\Console\Commands\AIWorker;
use KarlixLaravel\Console\Commands\RecoverOccurrences;
use KarlixLaravel\Console\Commands\SendReminders;
use KarlixLaravel\Http\CompletedResponse;
use KarlixLaravel\Http\Controllers\EndpointController;
use KarlixLaravel\Http\ExceptionRenderer;
use KarlixLaravel\Http\Middleware\Checks\ApiAuth;
use KarlixLaravel\Http\Middleware\PanelAccess;
use KarlixLaravel\Http\Middleware\ResolvePanelRoute;
use KarlixLaravel\Http\Response;

/** ورودی بک‌اند کاربر؛ هیچ handler کاربری به روتر یا Controller قدیمی ارسال نمی‌شود. */
final class Bridge
{
    public static function application(): Application
    {
        $routes = require dirname(__DIR__) . '/routes/panel.php';
        $app = Application::configure(basePath: dirname(__DIR__))
            ->withRouting(using: static function () use ($routes): void {
                Route::options('/{any?}', static function (): void {
                    ApiAuth::cors();
                    Response::noContent();
                })->where('any', '.*');

                foreach ($routes as $route) {
                    if (str_starts_with($route['path'], '/admin')) {
                        continue;
                    }
                    $middleware = [ResolvePanelRoute::class];
                    foreach ($route['mw'] as $name) {
                        $middleware[] = 'panel.check:' . $name;
                    }
                    Route::match([$route['method']], $route['path'], EndpointController::class)
                        ->defaults('_panel_method', $route['method'])
                        ->defaults('_panel_path', $route['path'])
                        ->defaults('_panel_handler', $route['handler'])
                        ->middleware($middleware);
                }
            })
            ->withCommands([RecoverOccurrences::class, SendReminders::class, AIWorker::class])
            ->withExceptions(static function (Exceptions $exceptions): void {
                $exceptions->dontReport([CompletedResponse::class, \App\Core\HttpException::class]);
                $exceptions->render(static fn (\Throwable $error) => ExceptionRenderer::render($error));
            })
            ->create();

        $app->instance('panel.routes', $routes);
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        // هدر، Session و تبدیل PID توسط ورودی مشترک و middlewareهای سازگار انجام می‌شوند.
        $kernel->setGlobalMiddleware([]);
        $app['router']->aliasMiddleware('panel.check', PanelAccess::class);
        $app['config']->set('database.default', 'karlix');
        $app['config']->set('database.connections.karlix', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'taskam'),
            'username' => env('DB_USER', ''),
            'password' => env('DB_PASS', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
        // یک PDO برای تراکنش‌های دامنه، audit و نشست مشترک؛ اتصال جداگانه برای قفل سهمیه حفظ می‌شود.
        $app['db']->connection('karlix')->setPdo(\App\Core\DB::pdo());
        date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Tehran'));
        \App\Core\ErrorHandler::register();
        return $app;
    }

    public static function dispatch(Router $catalog, Request $panel): void
    {
        $app = self::application();
        register_shutdown_function(static function (): void {
            try {
                \KarlixLaravel\Services\Push::sweepDue();
                \KarlixLaravel\Services\Push::flush();
            } catch (\Throwable $error) {
                \App\Core\ErrorHandler::log($error);
            }
        });
        $kernel = $app->make(Kernel::class);
        $incoming = \Illuminate\Http\Request::createFromGlobals();
        $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        $incoming->server->set('REQUEST_URI', $panel->path() . $query);
        $incoming->server->set('SCRIPT_NAME', '/index.php');
        $response = $kernel->handle($incoming);
        if ($app->resolved('session.store')) {
            $app['session.store']->save();
        }
        // PHP session/cookie و هدرهای سازگار قبلاً نوشته شده‌اند؛ ارسال دوباره CORS/CSP را خراب می‌کند.
        foreach (headers_list() as $line) {
            if (str_contains($line, ':')) {
                [$name] = explode(':', $line, 2);
                $response->headers->remove($name);
            }
        }
        $response->send();
        $kernel->terminate($incoming, $response);
    }
}
