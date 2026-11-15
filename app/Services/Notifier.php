<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;
use App\Models\UserModel;

/** ارسال اعلان‌های سیستمی از طریق «ربات کارلیکس» در چت. */
final class Notifier
{
    public static function send(int $toUserId, string $text): void
    {
        $bot = UserModel::botId();
        if ($toUserId <= 0 || $toUserId === $bot) {
            return;
        }
        try {
            \App\Models\ChatModel::create($bot, $toUserId, $text);
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
        }
    }

    /** اعلان ناشی از کار یک کاربر: اگر بین دو طرف مسدودی باشد ارسال نمی‌شود (مسیر مزاحمت بعد از مسدودسازی بسته بماند). */
    private static function sendFrom(int $actorId, int $toUserId, string $text): void
    {
        if ($actorId > 0 && $actorId !== $toUserId && Access::isBlockedBetween($actorId, $toUserId)) {
            return;
        }
        self::send($toUserId, $text);
    }

    public static function taskAssigned(array $task, int $actorId, ?string $projectTitle): void
    {
        $assignee = (int) ($task['assignee_id'] ?? 0);
        if ($assignee <= 0 || $assignee === $actorId || empty($task['share_token'])) {
            return;
        }
        self::taskAssignedTo($task, $actorId, $projectTitle, $assignee);
    }

    private static function taskAssignedTo(array $task, int $actorId, ?string $projectTitle, int $assignee): void
    {
        $actor = UserModel::find($actorId);
        $pr = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'][$task['priority']] ?? $task['priority'];
        if (isset($task['priority_percent']) && Priority::projectType(isset($task['project_id']) ? (int) $task['project_id'] : null) === 'percent') {
            $pr = fa_num((int) $task['priority_percent']) . '٪';
        }
        $lines = ['📌 وظیفه جدیدی برای شما ایجاد شد', '', 'عنوان: ' . $task['title'], 'ایجاد کننده: ' . ($actor ? full_name($actor) : 'کاربر')];
        if ($projectTitle) {
            $lines[] = 'پروژه: ' . $projectTitle;
        }
        $lines[] = 'اولویت: ' . $pr;
        if (!empty($task['due_date'])) {
            $d = Jalali::format($task['due_date'] . ' 00:00:00', false);
            if (!empty($task['due_time'])) {
                $d .= ' - ' . fa_num(substr((string) $task['due_time'], 0, 5));
            }
            $lines[] = 'مهلت: ' . $d;
        }
        if (!empty($task['description'])) {
            $lines[] = '';
            $lines[] = 'توضیحات: ' . mb_substr(trim((string) $task['description']), 0, 200, 'UTF-8');
        }
        self::sendFrom($actorId, $assignee, implode("\n", $lines) . "\n\n[[task:" . $task['share_token'] . ']]');
    }

    public static function noteMention(array $task, int $actorId, string $noteText, int $mentionedId, bool $mentioned = false): void
    {
        $actor = UserModel::find($actorId);
        $msg = ($mentioned ? '📣 شما در یادداشت وظیفه «' . $task['title'] . '» منشن شدید' : '📝 یادداشت جدید در وظیفه «' . $task['title'] . '»') . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'کاربر')
            . "\n\n" . mb_substr(trim($noteText), 0, 220, 'UTF-8') . "\n\n[[task:" . $task['share_token'] . ']]';
        self::sendFrom($actorId, $mentionedId, $msg);
    }

    /** پیوند اشتراکی تسک برای پیام ربات */
    private static function token(array $task): string
    {
        return (string) ($task['share_token'] ?: \App\Models\TaskModel::ensureShareToken((int) $task['id']));
    }

    /** مسئول تسک عوض شد (v7.10) → اطلاع به مسئول قبلی که دیگر این کار با او نیست */
    public static function taskReassignedAway(array $task, int $actorId, int $previousId, ?string $newName): void
    {
        if ($previousId <= 0 || $previousId === $actorId || $previousId === (int) ($task['assignee_id'] ?? 0)) {
            return;
        }
        $actor = UserModel::find($actorId);
        self::sendFrom($actorId, $previousId, '🔁 تسک «' . $task['title'] . '» به ' . ($newName ?: 'عضو دیگری') . ' واگذار شد و دیگر مسئولش شما نیستید.'
            . "

از طرف: " . ($actor ? full_name($actor) : 'کاربر') . "

[[task:" . self::token($task) . ']]');
    }

    /** تسک به «بررسی سرپرست» رسید → برای همه‌ی کسانی که می‌توانند تکمیلش کنند */
    public static function taskInReview(array $task, int $actorId, array $toIds): void
    {
        if (!$toIds) {
            return;
        }
        $actor = UserModel::find($actorId);
        $pt = !empty($task['project_id']) ? (string) \App\Core\DB::value('SELECT title FROM projects WHERE id = ?', [(int) $task['project_id']]) : '';
        $msg = '🔎 تسکی برای بررسی سرپرست آماده است' . "\n\nعنوان: " . $task['title'] . ($pt !== '' ? "\nپروژه: " . $pt : '')
            . "\nارسال‌کننده: " . ($actor ? full_name($actor) : 'کاربر') . "\n\nلطفاً بررسی کنید و تأیید (انجام‌شده) یا برای اصلاح به «بازبینی مسئول» برگردانید."
            . "\n\n[[task:" . self::token($task) . ']]';
        foreach (array_unique($toIds) as $uid) {
            self::sendFrom($actorId, (int) $uid, $msg);
        }
    }

    /** سرپرست تسک را به «بازبینی مسئول» برگرداند → برای مسئول تسک (یا سازنده اگر مسئول ندارد) */
    public static function taskReturned(array $task, int $actorId, ?string $reason): void
    {
        $to = [(int) ($task['assignee_id'] ?: $task['user_id'])];
        $actor = UserModel::find($actorId);
        $msg = '↩️ تسک «' . $task['title'] . '» برای اصلاح به «بازبینی مسئول» برگشت' . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'سرپرست')
            . ($reason ? "\nعلت: " . $reason : '') . "\n\nپس از اصلاح، تسک را دوباره به «بررسی سرپرست» بفرستید.\n\n[[task:" . self::token($task) . ']]';
        foreach (array_unique($to) as $u) {
            if ($u > 0 && $u !== $actorId) {
                self::sendFrom($actorId, $u, $msg);
            }
        }
    }

}
