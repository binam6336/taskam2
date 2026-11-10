<?php
declare(strict_types=1);

/**
 * داده‌ی نمونه‌ی «شرکت ۳۰ نفره در ۶ ماه» برای تست بخش‌ها زیر بار واقعی (فقط CLI؛ هرگز روی سرور اصلی اجرا نکنید):
 *   php bin/seed-demo.php --yes            ساخت (۷۰۰ تسک؛ با --tasks=N تعداد دیگر، --days=N طول دوره، --seed=N تکرارپذیری)
 *   php bin/seed-demo.php --clean --yes    حذف همه‌ی داده‌ی نمونه (پروژه‌های نمونه با همه‌ی تسک‌ها و کاربران نمونه)
 *
 * ساختار: مدیر کل = کاربر آزمایشی 09991867331 (سازنده‌ی همه‌ی پروژه‌های نمونه)، ۵ تیم/پروژه هر کدام با یک سرپرست (نقش «مدیر واحد»)
 * و اعضای «عضو»؛ کاربران آزمایشی موجود + کاربران نمونه‌ی 0999100xxxx (رمز همه: Demo12345) تا مجموع اعضا ۳۰ نفر شود.
 * هر تسک چرخه‌ی واقعی دارد: ساخت (روزهای کاری، ساعت اداری) ← شروع ← تحویل برای بررسی ← تأیید سرپرست یا برگشت برای اصلاح ← آرشیو؛
 * تسک‌هایی که چرخه‌شان به «امروز» نرسیده در مرحله‌ی میانی می‌مانند (برنامه‌ریزی‌نشده، آماده، در حال انجام، بررسی، بازبینی مسئول).
 * سرعت و دقت هر نفر متفاوت است تا KPI و تحلیل‌ها معنادار باشند. تاریخچه (task_events) و یادداشت‌ها هم ساخته می‌شوند. اعلانی فرستاده نمی‌شود.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Services\Access;

const MARK = '[داده‌ی نمونه]';
const DEMO_PREFIX = '0999100';
const MANAGER_MOBILE = '09991867331';

$opt = getopt('', ['yes', 'clean', 'tasks::', 'days::', 'seed::']);
if (!isset($opt['yes'])) {
    fwrite(STDERR, "این اسکریپت داده‌ی نمونه می‌سازد/حذف می‌کند. برای اجرا --yes بدهید (فقط روی دیتابیس تست).\n");
    exit(1);
}

$out = static fn(string $m) => fwrite(STDOUT, $m . "\n");

/* ───────────── حذف ───────────── */
$clean = static function () use ($out): void {
    $pids = array_map('intval', DB::column('SELECT id FROM projects WHERE description LIKE ?', ['%' . MARK . '%']));
    $n = 0;
    if ($pids) {
        $in = DB::in($pids);
        $n = (int) DB::value("SELECT COUNT(*) FROM tasks WHERE project_id IN ($in)", $pids);
        DB::run("DELETE FROM tasks WHERE project_id IN ($in)", $pids);           // یادداشت‌ها، تاریخچه، منشن‌ها CASCADE
        DB::run("DELETE FROM projects WHERE id IN ($in)", $pids);
    }
    $u = DB::run("DELETE FROM users WHERE mobile LIKE ?", [DEMO_PREFIX . '%']);
    // لیبل‌های تیم‌های نمونه که دیگر تسکی ندارند (لیبل‌های کاربران نمونه با حذف خودشان پاک شده‌اند)
    $titles = ['بک‌اند', 'فرانت‌اند', 'رفع باگ', 'تست', 'استقرار', 'اندروید', 'iOS', 'طراحی', 'انتشار', 'محتوا', 'شبکه اجتماعی', 'تبلیغات', 'گزارش', 'جلسه',
        'تیکت', 'تماس', 'آموزش', 'شکایت', 'سرور', 'امنیت', 'پایش', 'پشتیبان‌گیری', 'شبکه'];
    DB::run('DELETE FROM subjects WHERE title IN (' . DB::in($titles) . ') AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.subject_id = subjects.id)', $titles);
    $out("حذف شد: " . count($pids) . " پروژه، $n تسک، $u کاربر نمونه.");
};

if (isset($opt['clean'])) {
    $clean();
    exit(0);
}

$taskCount = max(50, min(5000, (int) ($opt['tasks'] ?? 700)));
$days = max(30, min(730, (int) ($opt['days'] ?? 182)));
mt_srand((int) ($opt['seed'] ?? 1405));

if (DB::value('SELECT 1 FROM projects WHERE description LIKE ? LIMIT 1', ['%' . MARK . '%'])) {
    $out('داده‌ی نمونه از قبل هست؛ دوباره ساخته می‌شود.');
    $clean();
}

$manager = DB::one('SELECT id FROM users WHERE mobile = ?', [MANAGER_MOBILE]);
if ($manager === null) {
    fwrite(STDERR, "کاربر مدیر (" . MANAGER_MOBILE . ") پیدا نشد.\n");
    exit(1);
}
$mgr = (int) $manager['id'];
$now = time();
$start = $now - $days * 86400;
$ts = static fn(int $t) => date('Y-m-d H:i:s', $t);
$pick = static fn(array $a) => $a[array_rand($a)];
$rand = static fn(float $a, float $b) => $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
/** لگ‌نرمال ساده: میانه‌ی $med، پراکندگی $s */
$logn = static function (float $med, float $s) use ($rand): float {
    $u1 = max(1e-9, mt_rand() / mt_getrandmax());
    $u2 = mt_rand() / mt_getrandmax();
    return $med * exp($s * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2));
};
/** زمان کاری: جمعه و بعدازظهر پنجشنبه به روز کاری بعد می‌رود، ساعت ۸ تا ۱۷ */
$workTime = static function (int $t) use ($rand): int {
    for ($i = 0; $i < 4; $i++) {
        $w = (int) date('w', $t);                                  // 5 = جمعه
        $h = (int) date('G', $t);
        if ($w === 5 || ($w === 4 && $h >= 13)) {
            $t = strtotime(date('Y-m-d', $t) . ' 08:00:00') + 86400 * ($w === 5 ? 1 : 2) + (int) $rand(0, 3 * 3600);
            continue;
        }
        if ($h < 8) {
            $t = strtotime(date('Y-m-d', $t) . ' 08:00:00') + (int) $rand(0, 2 * 3600);
        } elseif ($h >= 17) {
            $t = strtotime(date('Y-m-d', $t) . ' 08:00:00') + 86400 + (int) $rand(0, 2 * 3600);
            continue;
        }
        break;
    }
    return $t;
};

/* ───────────── کاربران (۳۰ عضو) ───────────── */
$existing = array_map('intval', DB::column("SELECT id FROM users WHERE is_bot = 0 AND role = 'user' AND status = 'active' AND id <> ? AND mobile NOT LIKE ? ORDER BY id", [$mgr, DEMO_PREFIX . '%']));
$names = [['امیر', 'حسینی'], ['زهرا', 'موسوی'], ['رضا', 'کاظمی'], ['فاطمه', 'جعفری'], ['محمد', 'صادقی'], ['مینا', 'رحیمی'], ['حسین', 'نوری'], ['لیلا', 'شریفی'],
    ['کیان', 'اکبری'], ['پریسا', 'قاسمی'], ['مهدی', 'زارعی'], ['نازنین', 'عباسی'], ['امید', 'یوسفی'], ['شیما', 'طاهری'], ['بابک', 'فرهادی'], ['رویا', 'مرادی'],
    ['سعید', 'کریمیان'], ['هانیه', 'نظری'], ['پویا', 'رستمی'], ['ترانه', 'امینی'], ['یاسر', 'بهرامی'], ['ساناز', 'پاکزاد'], ['کامران', 'سلیمانی'], ['آیدا', 'ملکی'],
    ['نیما', 'شاکری'], ['الناز', 'وحیدی'], ['فرزاد', 'حیدری'], ['مهسا', 'خسروی'], ['بردیا', 'نیک‌نام']];
$need = max(0, 29 - count($existing));
$pass = Auth::hash('Demo12345');
$demo = [];
for ($i = 0; $i < $need; $i++) {
    [$f, $l] = $names[$i % count($names)];
    $demo[] = DB::insert('users', ['mobile' => DEMO_PREFIX . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'first_name' => $f, 'last_name' => $l,
        'password_hash' => $pass, 'role' => 'user', 'status' => 'active', 'tour' => 'done', 'created_at' => $ts($start - 7 * 86400)]);
}
$staff = array_slice(array_merge($existing, $demo), 0, 29);                 // ۲۹ نفر + مدیر = ۳۰
$out('اعضا: ' . (count($staff) + 1) . ' نفر (' . count($demo) . ' کاربر نمونه‌ی جدید، رمز Demo12345).');

/* ───────────── تیم‌ها / پروژه‌ها ───────────── */
$teams = [
    ['سامانه فروش آنلاین', 'category', 0.26, ['بک‌اند', 'فرانت‌اند', 'رفع باگ', 'تست', 'استقرار'],
        ['پیاده‌سازی', 'رفع باگ', 'بازنویسی', 'بهینه‌سازی', 'تست', 'بررسی کد'], ['صفحه‌ی سبد خرید', 'درگاه پرداخت', 'API محصولات', 'فیلتر جستجو', 'پنل فروشنده', 'سیستم تخفیف', 'گزارش فروش', 'صفحه‌ی پرداخت موبایل', 'کش محصولات', 'وب‌هوک انبار']],
    ['اپلیکیشن موبایل مشتریان', 'percent', 0.22, ['اندروید', 'iOS', 'طراحی', 'رفع باگ', 'انتشار'],
        ['طراحی', 'پیاده‌سازی', 'رفع کرش', 'تست', 'بهبود'], ['صفحه‌ی ورود', 'اعلان‌های Push', 'پروفایل کاربر', 'سبد خرید اپ', 'حالت آفلاین', 'پیگیری سفارش', 'تم تاریک', 'نسخه‌ی تبلت', 'اسکنر بارکد']],
    ['کمپین‌های بازاریابی', 'category', 0.18, ['محتوا', 'شبکه اجتماعی', 'تبلیغات', 'گزارش', 'جلسه'],
        ['تهیه‌ی', 'برنامه‌ریزی', 'تحلیل', 'طراحی', 'هماهنگی'], ['کمپین یلدا', 'پست‌های اینستاگرام', 'خبرنامه‌ی ماهانه', 'تبلیغات گوگل', 'ویدیوی معرفی', 'کمپین بازگشت مشتری', 'لندینگ جشنواره', 'گزارش ROI']],
    ['پشتیبانی و خدمات مشتری', 'category', 0.20, ['تیکت', 'تماس', 'آموزش', 'شکایت', 'گزارش'],
        ['پیگیری', 'پاسخ به', 'بررسی', 'مستندسازی', 'آموزش'], ['شکایت تأخیر ارسال', 'مرجوعی کالا', 'سؤالات پرتکرار', 'تیکت‌های معوق', 'تماس‌های ورودی', 'رضایت‌سنجی', 'راهنمای خرید', 'خطای پرداخت مشتری']],
    ['زیرساخت و DevOps', 'percent', 0.14, ['سرور', 'امنیت', 'پایش', 'پشتیبان‌گیری', 'شبکه'],
        ['راه‌اندازی', 'به‌روزرسانی', 'پایش', 'رفع مشکل', 'مستندسازی'], ['سرور دیتابیس', 'گواهی SSL', 'پشتیبان‌گیری شبانه', 'CI/CD', 'لاگ مرکزی', 'فایروال', 'مانیتورینگ', 'کلاستر کش', 'بازیابی فاجعه']],
];
// تقسیم اعضا: هر تیم یک سرپرست + اعضا؛ چند نفر در دو تیم
$pool = $staff;
shuffle($pool);
$sizes = [7, 6, 5, 6, 5];
$teamMembers = [];
$o = 0;
foreach ($sizes as $i => $n) {
    $teamMembers[$i] = array_slice($pool, $o, $n);
    $o += $n;
}
foreach (array_slice($pool, $o) as $k => $u) {
    $teamMembers[$k % 5][] = $u;
}
$teamMembers[4][] = $teamMembers[0][1];                                     // همکاری بین‌تیمی
$teamMembers[1][] = $teamMembers[3][2];

[$supTask, $supProj, $supBoard] = Access::rolePackage('unit_manager');
[$memTask, $memProj, $memBoard] = Access::rolePackage('member');
$projects = [];
foreach ($teams as $i => [$title, $ptype, $weight, $labels, $verbs, $objects]) {
    $pid = DB::insert('projects', ['user_id' => $mgr, 'title' => $title, 'description' => 'تیم «' . $title . '» ' . MARK, 'max_members' => 20, 'priority_type' => $ptype, 'status' => 1, 'created_at' => $ts($start - 5 * 86400)]);
    $mem = array_values(array_unique($teamMembers[$i]));
    $sup = $mem[0];
    foreach ($mem as $u) {
        $isSup = $u === $sup;
        DB::run('INSERT IGNORE INTO project_members (project_id, user_id, permissions, task_permissions, board_permissions, board_view_users, added_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$pid, $u, json_encode($isSup ? $supProj : $memProj), json_encode($isSup ? $supTask : $memTask), json_encode($isSup ? $supBoard : $memBoard), $isSup ? '"all"' : null, $ts($start - 5 * 86400)]);
    }
    $projects[] = ['id' => $pid, 'type' => $ptype, 'weight' => $weight, 'sup' => $sup, 'members' => $mem, 'labels' => $labels, 'verbs' => $verbs, 'objects' => $objects];
}
// همکاری تأییدشده: مدیر با همه، هم‌تیمی‌ها با هم
$pair = static function (int $a, int $b): void {
    if ($a === $b) {
        return;
    }
    DB::run('INSERT IGNORE INTO colleagues (user_id, colleague_user_id) VALUES (?, ?), (?, ?)', [$a, $b, $b, $a]);
};
foreach ($projects as $p) {
    foreach ($p['members'] as $u) {
        $pair($mgr, $u);
        foreach ($p['members'] as $v) {
            $pair($u, $v);
        }
    }
}
$out('پروژه‌ها: ' . count($projects) . ' تیم ساخته شد.');

/* ───────────── لیبل‌ها (هر سازنده لیبل‌های تیم خودش) ───────────── */
$labelCache = [];
$labelOf = static function (int $uid, string $title) use (&$labelCache): int {
    $k = $uid . '|' . $title;
    if (!isset($labelCache[$k])) {
        $colors = \App\Models\SubjectModel::COLORS;
        $id = (int) DB::value('SELECT id FROM subjects WHERE user_id = ? AND title = ? LIMIT 1', [$uid, $title]);
        $labelCache[$k] = $id ?: DB::insert('subjects', ['user_id' => $uid, 'title' => $title, 'color' => $colors[crc32($title) % count($colors)]]);
    }
    return $labelCache[$k];
};

/* ───────────── ویژگی افراد: سرعت (ضریب زمان) و دقت (احتمال برگشت) ───────────── */
$speed = [];
$quality = [];
foreach (array_merge([$mgr], $staff) as $u) {
    $speed[$u] = max(0.35, min(3.0, $logn(1.0, 0.45)));
    $quality[$u] = max(0.03, min(0.35, $rand(0.04, 0.30)));
}
$reviewLag = [];                                                           // سرعت بررسی هر سرپرست (ساعت، میانه)
foreach ($projects as $p) {
    $reviewLag[$p['sup']] = $rand(2, 30);
}
$reviewLag[$mgr] = 20;

/* ───────────── تسک‌ها ───────────── */
$notesTxt = ['شروع کردم؛ تا آخر روز پیش‌نویس آماده است.', 'نیمی از کار انجام شد.', 'منتظر پاسخ تیم دیگر هستم.', 'مشکل اصلی پیدا شد و در حال رفع است.', 'نسخه‌ی اول آماده‌ی بررسی است.',
    'جلسه‌ی هماهنگی برگزار شد.', 'لطفاً اولویت را بررسی کنید.', 'فایل‌های مرتبط در پوشه‌ی مشترک است.', 'تست اولیه موفق بود.', 'یک وابستگی جدید پیدا شد.'];
$reworkTxt = ['خروجی با طرح نهایی یکی نیست', 'تست‌ها کامل نشده', 'چند مورد از چک‌لیست جا افتاده', 'متن‌ها نیاز به بازبینی دارد', 'روی موبایل درست نمایش داده نمی‌شود'];
$weights = array_column($projects, 'weight');
$wSum = array_sum($weights);
$stats = ['done' => 0, 'archived' => 0, 'review' => 0, 'rework' => 0, 'in_progress' => 0, 'todo' => 0, 'backlog' => 0, 'blocked' => 0, 'notes' => 0, 'events' => 0];

DB::pdo()->beginTransaction();
for ($n = 0; $n < $taskCount; $n++) {
    // پروژه (وزنی)
    $r = $rand(0, $wSum);
    foreach ($projects as $p) {
        $r -= $p['weight'];
        if ($r <= 0) {
            break;
        }
    }
    // زمان ساخت: کار شرکت کم‌کم بیشتر می‌شود (چگالی بیشتر در ماه‌های اخیر)
    $created = $workTime((int) ($start + ($now - $start) * sqrt($rand(0.02, 1))));
    if ($created > $now - 1800) {
        $created = $now - (int) $rand(1800, 86400);
    }
    $assignee = mt_rand(1, 100) <= 12 ? $p['sup'] : $pick(array_slice($p['members'], 1) ?: $p['members']);
    $cr = mt_rand(1, 100);
    $creator = $cr <= 55 ? $p['sup'] : ($cr <= 72 ? $mgr : $assignee);
    $reviewer = $assignee === $p['sup'] ? $mgr : $p['sup'];
    $prio = $pick(['high', 'medium', 'medium', 'medium', 'low', 'high', 'low', 'medium']);
    $pct = $p['type'] === 'percent' ? ['high' => mt_rand(14, 20), 'medium' => mt_rand(8, 13), 'low' => mt_rand(1, 7)][$prio] * 5 : null;
    $title = $pick($p['verbs']) . ' ' . $pick($p['objects']) . (mt_rand(1, 100) <= 35 ? ' — فاز ' . mt_rand(1, 3) : '');
    $label = mt_rand(1, 100) <= 82 ? $labelOf($p['sup'], $pick($p['labels'])) : null;   // لیبل‌های تیم = لیبل‌های سرپرست
    // سررسید
    $due = null;
    $dueTime = null;
    if (mt_rand(1, 100) <= 80) {
        $dd = ['high' => [0, 3], 'medium' => [2, 8], 'low' => [4, 16]][$prio];
        $dueTs = $workTime($created + mt_rand($dd[0], $dd[1]) * 86400 + 3600);
        $due = date('Y-m-d', $dueTs);
        $dueTime = mt_rand(1, 100) <= 45 ? sprintf('%02d:%s:00', mt_rand(10, 18), $pick(['00', '30'])) : null;
    }
    // چرخه: انتخاب ← شروع ← تحویل ← بررسی (تأیید یا برگشت) ...
    $base = ['high' => 14, 'medium' => 34, 'low' => 60][$prio];
    $sp = $speed[$assignee];
    $tl = [];                                                              // [زمان، از، به، کاربر، علت]
    $tTodo = $workTime($created + (int) ($logn(['high' => 4, 'medium' => 30, 'low' => 70][$prio], 1.0) * 3600));
    $tProg = $workTime($tTodo + (int) ($logn(14, 1.0) * 3600 * $sp));
    // صف کار: بخشی از تسک‌های ماه‌های اخیر هنوز انتخاب نشده‌اند (برنامه‌ریزی‌نشده)
    if ($created > $now - 50 * 86400 && mt_rand(1, 100) <= 18) {
        $tTodo = $now + 86400 * 30;
        $tProg = $tTodo + 3600;
    }
    $tl[] = [$tTodo, 'backlog', 'todo', $assignee, null];
    $tl[] = [$tProg, 'todo', 'in_progress', $assignee, null];
    $t = $tProg;
    $cur = 'in_progress';
    $delivered = null;
    $approved = null;
    $notesAt = [];
    for ($round = 0; $round < 3; $round++) {
        $t = $workTime($t + (int) ($logn($base, 0.7) * 3600 * $sp * ($round ? 0.35 : 1)));
        $tl[] = [$t, $cur, 'review', $assignee, null];
        $delivered = $t;
        $t = $workTime($t + (int) ($logn($reviewLag[$reviewer], 0.8) * 3600));
        if ($round < 2 && $rand(0, 1) < $quality[$assignee]) {
            $tl[] = [$t, 'review', 'rework', $reviewer, $pick($reworkTxt)];
            $cur = 'rework';
            $t = $workTime($t + (int) ($logn(3, 0.6) * 3600 * $sp));
            continue;
        }
        $tl[] = [$t, 'review', 'done', $reviewer, null];
        $approved = $t;
        break;
    }
    // وضعیت «امروز»: فقط رویدادهای گذشته
    $past = array_values(array_filter($tl, static fn($e) => $e[0] <= $now));
    $status = $past ? end($past)[2] : (mt_rand(1, 100) <= 70 ? 'backlog' : 'todo');
    if (!$past && $status === 'todo') {
        $past[] = [min($now - 600, $created + 3600), 'backlog', 'todo', $assignee, null];
    }
    $isDone = $status === 'done';
    $lastMove = $past ? end($past)[0] : $created;
    $deliveredAt = null;
    $deliveredBy = null;
    if (in_array($status, ['review', 'done'], true)) {
        foreach ($past as $e) {
            if ($e[2] === 'review') {
                $deliveredAt = $e[0];
                $deliveredBy = $e[3];
            }
        }
    }
    $archived = $isDone && $approved < $now - 30 * 86400 && mt_rand(1, 100) <= 60 ? $workTime($approved + mt_rand(3, 20) * 86400) : null;
    if ($archived !== null && $archived > $now) {
        $archived = null;
    }
    $reason = $status === 'rework' ? end($past)[4] : null;
    // ۳٪ تسک‌ها وسط کار لغو و «مسدود» شده‌اند (به‌جای حذف)
    $blocked = !$isDone && mt_rand(1, 100) <= 25 ? $workTime($lastMove + mt_rand(1, 5) * 86400) : null;
    if ($blocked !== null && ($blocked > $now || $lastMove > $now - 20 * 86400)) {
        $blocked = null;
    }
    $tid = DB::insert('tasks', [
        'user_id' => $creator, 'assignee_id' => $assignee, 'subject_id' => $label, 'project_id' => $p['id'], 'title' => $title,
        'description' => mt_rand(1, 100) <= 55 ? 'شرح کار: ' . $title . '. معیار اتمام: بررسی و تأیید سرپرست تیم.' : null,
        'priority' => $prio, 'priority_percent' => $pct, 'is_completed' => $isDone ? 1 : 0, 'board_status' => $status, 'blocked_reason' => $reason,
        'board_moved_at' => $ts($lastMove), 'archived_at' => $archived ? $ts($archived) : null,
        'blocked_at' => $blocked ? $ts($blocked) : null, 'blocked_by' => $blocked ? $p['sup'] : null,
        'due_date' => $due, 'due_time' => $dueTime, 'notified_at' => $due && strtotime($due) < $now ? $ts(strtotime($due)) : null,
        'share_token' => bin2hex(random_bytes(16)), 'created_at' => $ts($created),
        'completed_at' => $isDone ? $ts($approved) : null, 'delivered_at' => $deliveredAt ? $ts($deliveredAt) : null, 'delivered_by' => $deliveredBy,
        'approved_by' => $isDone ? $reviewer : null,
    ]);
    // v7.7: اولین تحویل و تعداد برگشت برای اصلاح (KPI) از همان تاریخچه
    $firstDel = null;
    $reworks = 0;
    foreach ($past as [$et, , $to]) {
        if (($to === 'review' || $to === 'done') && $firstDel === null) {
            $firstDel = $et;
        }
        $reworks += $to === 'rework' ? 1 : 0;
    }
    DB::run('UPDATE tasks SET first_delivered_at = ?, rework_count = ? WHERE id = ?', [$firstDel !== null ? $ts($firstDel) : ($deliveredAt ? $ts($deliveredAt) : null), $reworks, $tid]);
    // تاریخچه
    DB::insert('task_events', ['task_id' => $tid, 'user_id' => $creator, 'type' => 'created', 'data' => json_encode(['title' => $title], JSON_UNESCAPED_UNICODE), 'created_at' => $ts($created)]);
    foreach ($past as [$et, $from, $to, $by, $why]) {
        DB::insert('task_events', ['task_id' => $tid, 'user_id' => $by, 'type' => 'board', 'data' => json_encode(['from' => $from, 'to' => $to, 'reason' => $why], JSON_UNESCAPED_UNICODE), 'created_at' => $ts($et)]);
        $stats['events']++;
        if ($to === 'rework') {
            DB::insert('task_notes', ['task_id' => $tid, 'user_id' => $by, 'note' => "↩️ برگشت به «بازبینی مسئول» برای اصلاح\nعلت: " . $why, 'created_at' => $ts($et)]);
            $stats['notes']++;
        }
    }
    if ($archived) {
        DB::insert('task_events', ['task_id' => $tid, 'user_id' => $p['sup'], 'type' => 'archived', 'data' => null, 'created_at' => $ts($archived)]);
    }
    if ($blocked) {
        DB::insert('task_events', ['task_id' => $tid, 'user_id' => $p['sup'], 'type' => 'blocked', 'data' => null, 'created_at' => $ts($blocked)]);
    }
    // یادداشت‌های پیشرفت
    $k = mt_rand(1, 100) <= 40 ? mt_rand(1, 3) : 0;
    for ($j = 0; $j < $k; $j++) {
        $nt = (int) $rand($tProg, min($now, $delivered ?? $now));
        if ($nt <= $created || $nt > $now) {
            continue;
        }
        DB::insert('task_notes', ['task_id' => $tid, 'user_id' => mt_rand(1, 100) <= 75 ? $assignee : $reviewer, 'note' => $pick($notesTxt), 'created_at' => $ts($workTime($nt))]);
        $stats['notes']++;
    }
    $stats[$blocked ? 'blocked' : ($archived ? 'archived' : $status)]++;
}
DB::pdo()->commit();

$out("تسک‌ها: $taskCount (بازه‌ی $days روز).");
foreach ($stats as $k => $v) {
    $out("  $k: $v");
}
$out('ورود مدیر: ' . MANAGER_MOBILE . ' (رمز قبلی خودش)؛ سرپرست‌ها و اعضا: کاربران آزمایشی یا 0999100xxxx با رمز Demo12345.');
foreach ($projects as $p) {
    $s = DB::one('SELECT mobile, first_name, last_name FROM users WHERE id = ?', [$p['sup']]);
    $out('  سرپرست «' . DB::value('SELECT title FROM projects WHERE id = ?', [$p['id']]) . '»: ' . trim($s['first_name'] . ' ' . $s['last_name']) . ' (' . $s['mobile'] . ')');
}
