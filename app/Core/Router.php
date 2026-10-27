<?php
declare(strict_types=1);

namespace App\Core;

/** روتر ساده با پارامتر {name}، میدلور و گروه‌بندی. */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:array,mw:array}> */
    private array $routes = [];
    private array $groupMw = [];
    private string $prefix = '';

    private const MW = [
        'auth'   => \App\Middleware\Authenticate::class,
        'authany' => \App\Middleware\AuthAny::class,
        'panel'  => \App\Middleware\UserPanel::class,
        'admin'  => \App\Middleware\RequireAdmin::class,
        'guest'  => \App\Middleware\Guest::class,
        'csrf'   => \App\Middleware\VerifyCsrf::class,
        'api'    => \App\Middleware\ApiAuth::class,
    ];

    public function group(array $mw, callable $fn, string $prefix = ''): void
    {
        $oldMw = $this->groupMw;
        $oldPrefix = $this->prefix;
        $this->groupMw = array_merge($this->groupMw, $mw);
        $this->prefix .= $prefix;
        $fn($this);
        $this->groupMw = $oldMw;
        $this->prefix = $oldPrefix;
    }

    public function get(string $path, array $handler, array $mw = []): void
    {
        $this->add('GET', $path, $handler, $mw);
    }

    public function post(string $path, array $handler, array $mw = []): void
    {
        $this->add('POST', $path, $handler, $mw);
    }

    private function add(string $method, string $path, array $handler, array $mw): void
    {
        $full = '/' . trim($this->prefix . $path, '/');
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $full);
        $all = array_merge($this->groupMw, $mw);
        // CSRF برای تمام درخواست‌های تغییردهنده‌ی وب (API توکنی در ApiAuth بررسی می‌شود)
        if ($method !== 'GET' && !in_array('api', $all, true) && !in_array('csrf', $all, true)) {
            $all[] = 'csrf';
        }
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#u', 'handler' => $handler, 'mw' => $all];
    }

    public function dispatch(Request $req): void
    {
        $method = $req->method();
        $path = $req->path();
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        if ($method === 'OPTIONS' && str_starts_with($path, '/api/')) {
            \App\Middleware\ApiAuth::cors();
            Response::noContent();
        }
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($r['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }
            $req->params = $params;
            foreach ($r['mw'] as $name) {
                $cls = self::MW[$name] ?? null;
                if ($cls === null) {
                    throw new \LogicException('Unknown middleware: ' . $name);
                }
                $cls::handle($req);
            }
            [$class, $action] = $r['handler'];
            $controller = new $class($req);
            $controller->$action(...array_values($params));
            return;
        }
        if ($pathMatched) {
            throw new HttpException(405, 'متد درخواست مجاز نیست.', 'METHOD_NOT_ALLOWED');
        }
        throw new HttpException(404, 'صفحه‌ی مورد نظر یافت نشد.', 'NOT_FOUND');
    }
}
