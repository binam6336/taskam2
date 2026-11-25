<?php
declare(strict_types=1);
// کران اختیاری هر دقیقه؛ دسترسی‌های لغوشده و پایان ناقص جلسه را حتی بدون مرورگر همگام می‌کند.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
if (!\App\Core\LiveKitConfig::configured()) { fwrite(STDERR, "LiveKit Cloud تنظیم نشده است.\n"); exit(1); }
$result = (new \KarlixLaravel\Services\Meetings\MeetingService())->sweep();
echo json_encode($result) . "\n";
exit($result['failed'] ? 1 : 0);
