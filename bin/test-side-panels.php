<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Core\Crypto;
use App\Core\ContentCipher;
use App\Core\HttpException;
use App\Services\SidePanels;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
function checkPanel(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function rejectPanel(callable $fn, int $status, string $label): void {
    try { $fn(); } catch (HttpException $e) { checkPanel($e->status === $status, $label); return; }
    throw new RuntimeException($label);
}
DB::pdo()->beginTransaction();
try {
    $uid = (int) DB::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $data = ['title' => 'پنل صورتحساب', 'url' => 'https://example.com/panel', 'description' => 'مدیریت امور مالی', 'username' => 'test-user', 'password' => '  test#pass$\\  '];
    $id = SidePanels::save(null, $data, $uid);
    $key = SidePanels::PREFIX . $id;
    $raw = DB::pdo()->prepare('SELECT body FROM content_texts WHERE `key` = ?'); $raw->execute([$key]); $body = $raw->fetchColumn();
    checkPanel(Crypto::isEncoded($body) && !str_contains($body, 'test-user') && !str_contains($body, $data['title']), 'تمام اطلاعات پنل به‌صورت رمزگذاری‌شده ذخیره شد');
    checkPanel(Crypto::decode($body, ContentCipher::key(), $key . 'x') === null, 'انتقال ciphertext بین رکوردها پذیرفته نمی‌شود');
    checkPanel(SidePanels::credentials($id) === ['username' => $data['username'], 'password' => $data['password']], 'رمز با فاصله و نویسه‌های ویژه بدون تغییر برمی‌گردد');
    $list = SidePanels::listing(); $row = array_values(array_filter($list, static fn($r) => $r['id'] === $id))[0];
    checkPanel(!isset($row['username'], $row['password']) && $row['has_password'], 'فهرست شامل اطلاعات ورود نیست');
    $edited = $data; $edited['title'] = 'ویرایش'; $edited['password'] = ''; $edited['username'] = '';
    SidePanels::save($id, $edited, $uid);
    checkPanel(SidePanels::credentials($id)['password'] === $data['password'], 'ویرایش بدون ورود رمز، مقدار قبلی را نگه می‌دارد');
    $edited['clear_password'] = true; $edited['clear_username'] = true; SidePanels::save($id, $edited, $uid);
    checkPanel(SidePanels::credentials($id) === ['username' => '', 'password' => ''], 'پاک کردن صریح اطلاعات اختیاری');
    foreach (['javascript:alert(1)', 'https://user:pass@example.com', 'file:///tmp/test', 'https://example.com/ bad'] as $url) {
        rejectPanel(static fn() => SidePanels::save(null, array_replace($data, ['url' => $url]), $uid), 422, 'رد نشانی ناامن: ' . $url);
    }
    rejectPanel(static fn() => SidePanels::save(null, array_replace($data, ['title' => str_repeat('x', 151)]), $uid), 422, 'اعتبارسنجی طول ورودی');
    $broken = $body; $broken[strlen($broken)-5] = $broken[strlen($broken)-5] === 'A' ? 'B' : 'A';
    DB::run('UPDATE content_texts SET body = ? WHERE `key` = ?', [$broken, $key]);
    rejectPanel(static fn() => SidePanels::credentials($id), 422, 'داده دست‌کاری‌شده فاش نمی‌شود');
    rejectPanel(static fn() => SidePanels::save($id, $data, $uid), 422, 'داده غیرقابل رمزگشایی بازنویسی نمی‌شود');
    SidePanels::delete($id);
    rejectPanel(static fn() => SidePanels::credentials($id), 404, 'حذف رکورد');
    rejectPanel(static fn() => SidePanels::credentials('1'), 404, 'شناسه نامعتبر');
} finally { DB::pdo()->rollBack(); }
