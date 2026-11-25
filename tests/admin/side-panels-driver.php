<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Core\DB;
use App\Services\SidePanels;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$args = json_decode($argv[1] ?? '{}', true);
if (($args['action'] ?? '') === 'fixture') {
    $mobile = '099' . random_int(10000000, 99999999);
    $uid = DB::insert('users', ['mobile' => $mobile, 'first_name' => 'مدیر آزمایشی', 'password_hash' => password_hash('AdminTest987!', PASSWORD_DEFAULT),
        'status' => 'active', 'role' => 'admin', 'admin_role' => 'internal', 'admin_permissions' => '["edit_tour","support_view"]']);
    echo json_encode(['id' => $uid, 'mobile' => $mobile, 'password' => 'AdminTest987!', 'testDatabase' => env('DB_NAME')]);
} elseif (($args['action'] ?? '') === 'cleanup') {
    foreach (($args['panels'] ?? []) as $id) { DB::run('DELETE FROM content_texts WHERE `key` = ?', [SidePanels::PREFIX . $id]); }
    $uid = (int) ($args['user'] ?? 0);
    if ($uid && DB::value('SELECT first_name FROM users WHERE id = ?', [$uid]) === 'مدیر آزمایشی') { DB::run('DELETE FROM users WHERE id = ?', [$uid]); }
    echo '{}';
}
