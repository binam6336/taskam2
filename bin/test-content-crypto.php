<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Core\Env;
use App\Core\ContentCipher as C;
use App\Core\Search;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$f=tempnam(sys_get_temp_dir(),'cryptotest');file_put_contents($f,"decode_key=test-only-content-key-12345678901234567890\n");Env::load($f);unlink($f);
$uid=0;
function check_crypto($ok,$label) { if(!$ok)throw new RuntimeException($label);echo "PASS $label\n"; }
try {
$uid=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'تست','password_hash'=>'test','status'=>'active']);
$p=DB::insert('projects',['user_id'=>$uid,'title'=>'پروژه','description'=>'توضیحات خصوصی']);
$t=DB::insert('tasks',['user_id'=>$uid,'assignee_id'=>$uid,'project_id'=>$p,'title'=>'گزارش آزمایشی محرمانه','description'=>'متن خصوصی']);
$raw=DB::pdo()->query('SELECT title FROM tasks WHERE id='.$t)->fetchColumn();
check_crypto(str_starts_with($raw,C::PREFIX)&&!str_contains($raw,'گزارش'),'عدم ذخیره نام تسک به شکل متن ساده');
check_crypto(DB::value('SELECT title FROM tasks WHERE id=?',[$t])==='گزارش آزمایشی محرمانه','خواندن تکی');
check_crypto(DB::one('SELECT t.title AS task_title FROM tasks t WHERE id=?',[$t])['task_title']==='گزارش آزمایشی محرمانه','خواندن alias');
[$where,$params]=Search::sql(['t.title','t.description'],'آزمایشی محرمانه');
check_crypto((int)DB::value('SELECT COUNT(*) FROM tasks t WHERE user_id=? AND '.$where,array_merge([$uid],$params))===1,'جستجو و شمارش روی نام رمزگذاری‌شده');
DB::update('tasks',['title'=>'عنوان جدید'],'id=?',[$t]);
check_crypto(DB::column('SELECT title FROM tasks WHERE id=?',[$t])===['عنوان جدید'],'ویرایش و خواندن ستون');
check_crypto(C::seal('متن')!==C::seal('متن'),'nonce تصادفی');
$x=C::seal('متن');$x[strlen($x)-5]=$x[strlen($x)-5]==='A'?'B':'A';
try { C::open($x);throw new RuntimeException('tamper accepted'); } catch(RuntimeException $e) { check_crypto($e->getMessage()!=='tamper accepted','رد داده دست‌کاری‌شده'); }
DB::pdo()->prepare('UPDATE tasks SET title=? WHERE id=?')->execute(['قدیمی',$t]);
// تبدیل فقط fixture، بدون تغییر داده‌های دیگر دیتابیس تست.
$st=DB::pdo()->prepare('UPDATE tasks SET title=? WHERE id=? AND title=?');$st->execute([C::seal('قدیمی'),$t,'قدیمی']);
check_crypto(DB::value('SELECT title FROM tasks WHERE id=?',[$t])==='قدیمی','خواندن داده تبدیل‌شده');
DB::pdo()->beginTransaction();$before=C::pending();$done=C::batch(10);check_crypto($before-C::pending()===$done,'تبدیل مرحله‌ای و شمارش باقیمانده');DB::pdo()->rollBack();
} finally { if($uid)DB::run('DELETE FROM users WHERE id=?',[$uid]); }
