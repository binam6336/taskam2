<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$native = !in_array('--legacy', $argv, true);
if ($native) { require BASE_PATH . '/laravel/bootstrap/app.php'; }
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }

/** پاسخ قطعی به‌جای سرویس پولی؛ پرامپت و اجرای واقعی Assistant همچنان بررسی می‌شوند. */
final class ReminderModelFixture
{
    public static array $plan = [];
    public static array $context = [];
    public static function enabled(): bool { return true; }
    public static function timeout(): int { return 5; }
    public static function model(): string { return 'local-reminder-fixture'; }
    public static function chat(array $messages, int $maxTokens = 700, array $meta = []): array
    {
        if (!str_contains($messages[0]['content'], 'update_overdue_reminder') || !str_contains($messages[0]['content'], 'clear_overdue_reminder')
            || !str_contains($messages[0]['content'], 'هر 10 دقیقه') || !str_contains($messages[0]['content'], 'هر ۲ ساعت')) {
            throw new RuntimeException('پرامپت تنظیم یادآوری را به مدل معرفی نکرده است');
        }
        preg_match('/CONTEXT:\n(.*?)\n\nREQUEST/s', $messages[1]['content'], $m);
        self::$context = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        return ['ok' => true, 'content' => json_encode(self::$plan, JSON_UNESCAPED_UNICODE), 'tokens' => 10];
    }
}
$prefix = $native ? 'KarlixLaravel\\Services\\Karlix\\' : 'App\\Services\\Karlix\\';
class_alias(ReminderModelFixture::class, $prefix . 'Agnes');
$actions = $prefix . 'Actions'; $assistant = $prefix . 'Assistant';
$db = $native ? KarlixLaravel\Database\DB::class : App\Core\DB::class;
$tasks = $native ? KarlixLaravel\Repositories\TaskModel::class : App\Models\TaskModel::class;
$checks = 0;
function aiReminderCheck(bool $ok, string $label): void
{
    if (!$ok) { throw new RuntimeException($label); }
    $GLOBALS['checks']++; echo "PASS $label\n";
}
$db::pdo()->beginTransaction();
try {
    $users = [];
    foreach (['سازنده', 'عضو', 'بیگانه'] as $name) {
        $users[] = $db::insert('users', ['mobile' => '099' . random_int(10000000, 99999999), 'first_name' => $name, 'status' => 'active', 'password_hash' => 'fixture']);
    }
    [$owner, $member, $outsider] = $users;
    $project = $db::insert('projects', ['user_id' => $owner, 'title' => 'یادآوری هوش مصنوعی']);
    $db::insert('project_members', ['project_id' => $project, 'user_id' => $member, 'permissions' => '["view"]', 'task_permissions' => '["notes"]', 'board_view_users' => '"all"']);
    $task = $db::insert('tasks', ['user_id' => $owner, 'assignee_id' => $owner, 'project_id' => $project, 'title' => 'تسک آزمایشی',
        'due_date' => date('Y-m-d', strtotime('-1 day')), 'due_time' => '09:00', 'notified_at' => date('Y-m-d H:i:s')]);
    $ctx = ['labels' => [], 'members' => [], 'statuses' => [], 'project_id' => $project];
    $original = $tasks::raw($task);
    $execute = static function (string $ask, array $plan, int $actor) use ($db, $assistant, $task): array {
        ReminderModelFixture::$plan = $plan;
        $note = $db::insert('task_notes', ['task_id' => $task, 'user_id' => $actor, 'note' => '@کارلیکس ' . $ask]);
        static $queued = false;
        if (!$queued) {
            $q = $assistant::queue($task, $note, $actor, '@کارلیکس ' . $ask);
            aiReminderCheck(($q['queued'] ?? false) === true, 'یادداشت منشن‌شده از مسیر واقعی وارد صف می‌شود');
            $queued = true;
        } else {
            // بقیه سناریوها مستقل از سقف دقیقه‌ای صف آزمایش می‌شوند.
            $db::insert('ai_requests', ['task_id' => $task, 'note_id' => $note, 'user_id' => $actor, 'status' => 'pending']);
        }
        $r = $assistant::run($actor, $task, $note, null, true);
        $request = $db::one('SELECT * FROM ai_requests WHERE note_id=?', [$note]);
        return [$r['data']['state'], (string) $db::value('SELECT note FROM task_notes WHERE id=?', [$request['reply_note_id']]), $request];
    };
    $plan = static fn(array $list) => ['reply_type' => 'actions', 'actions' => $list, 'message' => ''];
    foreach ([['10', 'minute', 'یادآوریش هم بعد از گذشت زمان بذار هر 10 دقیقه'], ['۲', 'hour', 'اگه انجام نشد هر ۲ ساعت یادآوری کن'],
        [3, 'day', 'بعد از موعد هر ۳ روز'], [1, 'week', 'هر هفته یادآوری کن'], [1, 'month', 'در صورت تأخیر هر ماه یادآوری کن']] as [$every, $unit, $ask]) {
        [$state, $reply] = $execute($ask, $plan([['type' => 'update_overdue_reminder', 'every' => $every, 'unit' => $unit]]), $owner);
        $row = $tasks::raw($task);
        aiReminderCheck($state === 'done' && (int) $row['reminder_every'] === (int) App\Core\Validate::digits((string) $every) && $row['reminder_unit'] === $unit, 'ثبت و تأیید واقعی بازه ' . $unit);
        aiReminderCheck(str_contains($reply, 'یادآوری پس از گذشت موعد') && $row['due_date'] === $original['due_date'] && $row['due_time'] === $original['due_time']
            && $row['notified_at'] === $original['notified_at'] && $row['recurrence_id'] === null, 'پاسخ واقعی بدون تغییر موعد، ساخت نوبت تسک یا ریست اعلان ' . $unit);
    }
    aiReminderCheck(ReminderModelFixture::$context['TASK']['reminder_every'] === 1 && ReminderModelFixture::$context['TASK']['reminder_unit'] === 'week', 'مقدار قبلی بازه در زمینه مدل موجود است');
    $current = $tasks::raw($task);
    [$state] = $execute('هر ۲ ساعت', $plan([['type' => 'update_overdue_reminder', 'every' => 2, 'unit' => 'hour']]), $member);
    aiReminderCheck($state === 'failed' && $tasks::raw($task)['reminder_unit'] === $current['reminder_unit'], 'یادداشت‌گذار بدون edit_due مجاز به تغییر یادآوری نیست');
    [$state] = $execute('هر ۲ ساعت', $plan([['type' => 'update_overdue_reminder', 'every' => 2, 'unit' => 'hour']]), $outsider);
    aiReminderCheck($state === 'refused', 'کاربر بیگانه پیش از مدل رد می‌شود');
    [$state, $reply] = $execute('تکرار یادآوری تأخیر را خاموش کن', $plan([['type' => 'clear_overdue_reminder']]), $owner);
    $row = $tasks::raw($task);
    aiReminderCheck($state === 'done' && $row['reminder_every'] === null && $row['reminder_unit'] === null && $row['due_date'] === $original['due_date'], 'خاموش‌کردن تکرار بدون حذف موعد');
    $invalid = [[], ['every' => null], ['every' => ''], ['every' => 0], ['every' => -1], ['every' => 1.5], ['every' => 1001], ['every' => []], ['every' => true], ['every' => 2, 'unit' => []], ['every' => 2, 'unit' => 'year']];
    foreach ($invalid as $i => $raw) {
        [$ok, $bad] = $actions::validate([['type' => 'update_overdue_reminder'] + $raw + ['unit' => 'minute']], $ctx);
        aiReminderCheck(!$ok && count($bad) === 1, 'رد پارامتر نامعتبر مدل ' . $i);
    }
    [$ok, $bad] = $actions::validate([['type' => 'clear_due_date'], ['type' => 'update_overdue_reminder', 'every' => 10, 'unit' => 'minute']], $ctx);
    aiReminderCheck(!$ok && count($bad) === 1, 'حذف موعد و فعال‌کردن یادآوری هم‌زمان هیچ‌کدام اجرا نمی‌شوند');
    $db::update('tasks', ['due_date' => null, 'due_time' => null], 'id=?', [$task]);
    [$state] = $execute('بعد از موعد هر ده دقیقه', $plan([['type' => 'update_overdue_reminder', 'every' => 10, 'unit' => 'minute']]), $owner);
    aiReminderCheck($state === 'failed' && $tasks::raw($task)['reminder_every'] === null, 'اگر مدل بدون موعد اقدام کند سرویس از ثبت جلوگیری می‌کند');
    [$state] = $execute('بعد از موعد هر چند دقیقه یادآوری کن', ['reply_type' => 'clarify', 'actions' => [], 'message' => 'تاریخ انجام و فاصله یادآوری چقدر باشد؟'], $owner);
    aiReminderCheck($state === 'clarify' && $tasks::raw($task)['due_date'] === null, 'درخواست مبهم بدون تغییر با سؤال پاسخ داده می‌شود');
    $date = date('Y-m-d', strtotime('+2 days'));
    [$state] = $execute('پس‌فردا ساعت ۱۵ و بعدش هر ۱۰ دقیقه یادآوری کن', $plan([
        ['type' => 'update_overdue_reminder', 'every' => 10, 'unit' => 'minute'], ['type' => 'update_due_date', 'date' => $date, 'time' => '15:00']]), $owner);
    $row = $tasks::raw($task);
    aiReminderCheck($state === 'done' && $row['due_date'] === $date && $row['due_time'] === '15:00:00' && (int) $row['reminder_every'] === 10, 'موعد و بازه در یک پیام با ترتیب درست اجرا می‌شوند');
    aiReminderCheck((int) $db::value("SELECT COUNT(*) FROM task_events WHERE task_id=? AND type='reminder'", [$task]) >= 7, 'تاریخچه واقعی تغییر تنظیمات یادآوری ثبت می‌شود');
    echo "OK $checks checks (" . ($native ? 'Laravel' : 'legacy') . "); local model fixture, no external AI call\n";
} finally {
    if ($db::pdo()->inTransaction()) { $db::pdo()->rollBack(); }
}
