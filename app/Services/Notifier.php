<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;
use App\Models\UserModel;

/** ارسال اعلان‌های سیستمی از طریق «ربات تسکام» در چت. */
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

    public static function taskAssigned(array $task, int $actorId, ?string $projectTitle): void
    {
        $assignee = (int) ($task['assignee_id'] ?? 0);
        if ($assignee <= 0 || $assignee === $actorId || empty($task['share_token'])) {
            return;
        }
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
        self::send($assignee, implode("\n", $lines) . "\n\n[[task:" . $task['share_token'] . ']]');
    }

    public static function noteMention(array $task, int $actorId, string $noteText, int $mentionedId, bool $mentioned = false): void
    {
        $actor = UserModel::find($actorId);
        $msg = ($mentioned ? '📣 شما در یادداشت وظیفه «' . $task['title'] . '» منشن شدید' : '📝 یادداشت جدید در وظیفه «' . $task['title'] . '»') . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'کاربر')
            . "\n\n" . mb_substr(trim($noteText), 0, 220, 'UTF-8') . "\n\n[[task:" . $task['share_token'] . ']]';
        self::send($mentionedId, $msg);
    }

    public static function callAssigned(array $call, int $actorId): void
    {
        $assignee = (int) ($call['assignee_id'] ?? 0);
        if ($assignee <= 0 || $assignee === $actorId || empty($call['share_token'])) {
            return;
        }
        $actor = UserModel::find($actorId);
        $lines = ['📞 درخواست تماس جدیدی برای شما ثبت شد', '', 'نام: ' . trim($call['first_name'] . ' ' . $call['last_name']), 'موبایل: ' . $call['mobile']];
        if (!empty($call['store'])) {
            $lines[] = 'فروشگاه: ' . $call['store'];
        }
        $lines[] = 'ثبت‌کننده: ' . ($actor ? full_name($actor) : 'کاربر');
        self::send($assignee, implode("\n", $lines) . "\n\n[[call:" . $call['share_token'] . ']]');
    }
}
