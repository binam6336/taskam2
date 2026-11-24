<?php
declare(strict_types=1);

/** بازیابی تکرارهای متوقف‌شده، فقط سمت سرور: * * * * * php /path/to/karlix/bin/recurring-tasks.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (env('USER_ROUTER', 'laravel') === 'laravel') {
    $app = require dirname(__DIR__) . '/laravel/bootstrap/app.php';
    $arguments = array_merge([$argv[0], 'karlix:recurring'], array_slice($argv, 1));
    exit($app->handleCommand(new \Symfony\Component\Console\Input\ArgvInput($arguments)));
}

$count = \App\Services\TaskRecurrence::recover();
echo 'Created ' . $count . " recurring task occurrence(s).\n";
