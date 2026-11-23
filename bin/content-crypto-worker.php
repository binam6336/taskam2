<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$q = new App\Services\ContentEncryptionQueue();
$until = microtime(true) + (in_array('--once', $argv, true) ? 0 : 40);
do {
    $s = $q->tick();
    if ($s['state'] !== 'running' || microtime(true) >= $until) { break; }
    sleep(App\Services\ContentEncryptionQueue::INTERVAL);
} while (true);
echo json_encode($s, JSON_UNESCAPED_UNICODE) . PHP_EOL;
