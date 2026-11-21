<?php
declare(strict_types=1);

/** بازیابی تکرارهای متوقف‌شده، فقط سمت سرور: * * * * * php /path/to/karlix/bin/recurring-tasks.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$count = \App\Services\TaskRecurrence::recover();
echo 'Created ' . $count . " recurring task occurrence(s).\n";
