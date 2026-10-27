<?php
/**
 * یادآوری مهلت تسک‌ها با اعلان PWA (Web Push).
 * سایت این کار را با هر بازدید حداکثر دقیقه‌ای یک‌بار خودش انجام می‌دهد؛ برای دقت کامل حتی وقتی کسی سایت را باز نکرده،
 * این اسکریپت را هر دقیقه با کران‌جاب اجرا کنید:
 *     * * * * * php /path/to/taskam/bin/push-cron.php >/dev/null 2>&1
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$n = App\Services\Push::sweepDue(true);
App\Services\Push::flush();
echo date('Y-m-d H:i:s') . " — {$n} reminder(s) queued\n";
