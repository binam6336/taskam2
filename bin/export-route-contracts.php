<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$router = new App\Core\Router();
require BASE_PATH . '/config/routes.php';
$out = [];
foreach ($router->definitions() as $route) {
 if (str_starts_with($route['path'], '/admin')) { continue; }
 $out[] = ['method' => $route['method'], 'path' => $route['path'], 'middleware' => $route['mw'], 'handler' => implode('::', $route['handler'])];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
