<?php
declare(strict_types=1);
namespace App\Core;

/** برنامه یادآوری تأخیر؛ مستقل از ساخت نوبت تازه برای تسک تکرارشونده. */
final class ReminderSchedule
{
    public const MAX_EVERY = 1000;
    public const UNITS = ['minute' => 'دقیقه', 'hour' => 'ساعت', 'day' => 'روز', 'week' => 'هفته', 'month' => 'ماه'];
    public const FIELDS = ['reminder_every', 'reminder_unit'];

    public static function validate(array $input, ?array $old, ?string $due): array|string
    {
        $every = array_key_exists('reminder_every', $input) ? $input['reminder_every'] : ($old['reminder_every'] ?? null);
        $unit = $input['reminder_unit'] ?? ($old['reminder_unit'] ?? 'minute');
        if ($every === null || $every === '') { return ['reminder_every' => null, 'reminder_unit' => null]; }
        if ((!is_string($every) && !is_int($every)) || !is_string($unit)) { return 'بازه یادآوری تأخیر نامعتبر است.'; }
        $every = Validate::digits((string) $every);
        if (!preg_match('/^[0-9]+$/D', $every) || (int) $every < 1 || (int) $every > self::MAX_EVERY || !isset(self::UNITS[$unit])) {
            return 'بازه یادآوری تأخیر باید عدد صحیح ۱ تا ۱۰۰۰ و واحد معتبر باشد.';
        }
        if ($due === null) { return 'برای تکرار یادآوری تأخیر، ابتدا تاریخ انجام را مشخص کنید.'; }
        return ['reminder_every' => (int) $every, 'reminder_unit' => $unit];
    }

    /** شرط مشترک مرورگر، تأیید نمایش و Web Push؛ تمام بررسی‌ها هنگام گرفتن نوبت هم تکرار می‌شوند. */
    public static function eligible(string $t = 't'): string
    {
        $due = "CASE WHEN $t.due_time IS NULL THEN DATE_ADD(TIMESTAMP($t.due_date), INTERVAL 1 DAY) ELSE TIMESTAMP($t.due_date, $t.due_time) END";
        $next = 'CASE ' . $t . '.reminder_unit ';
        foreach (array_keys(self::UNITS) as $unit) { $next .= " WHEN '$unit' THEN DATE_ADD($t.notified_at, INTERVAL $t.reminder_every " . strtoupper($unit) . ')'; }
        $next .= ' ELSE NULL END';
        return "$t.is_completed = 0 AND $t.archived_at IS NULL AND $t.blocked_at IS NULL AND $t.due_date IS NOT NULL
            AND NOT ($t.project_id IS NOT NULL AND $t.board_status = 'review')
            AND ($t.project_id IS NULL OR EXISTS (SELECT 1 FROM projects rp WHERE rp.id = $t.project_id AND rp.blocked_at IS NULL
                AND (rp.user_id = $t.assignee_id OR EXISTS (SELECT 1 FROM project_members rpm WHERE rpm.project_id = rp.id AND rpm.user_id = $t.assignee_id))))
            AND ($due) <= NOW() AND ($t.notified_at IS NULL OR ($t.reminder_every > 0 AND ($next) <= NOW()))";
    }

    /** شناسه نوبت؛ با تغییر مسئول، سررسید، تنظیمات یا آخرین اعلان عوض می‌شود؛ حاوی اطلاعات داخلی نیست. */
    public static function ref(array $task): string
    {
        return hash('sha256', json_encode(array_map(static fn($key) => $task[$key] ?? null,
            ['id', 'assignee_id', 'due_date', 'due_time', 'reminder_every', 'reminder_unit', 'notified_at']), JSON_THROW_ON_ERROR));
    }

    public static function label(array $task): string
    {
        return empty($task['reminder_every']) ? 'بدون تکرار' : 'هر ' . fa_num($task['reminder_every']) . ' ' . (self::UNITS[$task['reminder_unit'] ?? ''] ?? '');
    }
}
