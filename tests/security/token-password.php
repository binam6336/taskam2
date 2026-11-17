<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Throttle;

// Integration test against the configured development database; all rows roll back.
$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    $password = 'Token test ' . bin2hex(random_bytes(16));
    $uid = DB::insert('users', [
        'mobile' => '09' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        'first_name' => 'Token password test', 'last_name' => '',
        'password_hash' => Auth::hash($password), 'role' => 'user', 'status' => 'active',
    ]);
    $check = static function (bool $ok, string $label): void {
        if (!$ok) {
            throw new RuntimeException($label);
        }
        echo "PASS $label\n";
    };
    foreach (['', 'wrong-password', str_repeat('x', 201)] as $bad) {
        $check(Auth::confirmTokenPassword($uid, $bad) !== null, 'missing, wrong or oversized password rejected');
    }
    $check(Auth::confirmTokenPassword($uid, $password) === null, 'correct password including spaces accepted');
    DB::update('users', ['password_hash' => Auth::hash('Replacement ' . $password)], 'id = ?', [$uid]);
    $check(Auth::confirmTokenPassword($uid, $password) !== null, 'old password rejected after password change');
    $check(Auth::confirmTokenPassword($uid, 'Replacement ' . $password) !== null, 'shared account limit rejects further attempts');
    Throttle::clear('token_password', (string) $uid);
    $check(Auth::confirmTokenPassword($uid, 'Replacement ' . $password) === null, 'current password accepted after limit clears');
} finally {
    $pdo->rollBack();
}
