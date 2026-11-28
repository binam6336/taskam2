<?php
declare(strict_types=1);
namespace App\Core;

/** واژگان مشترک پرامپت‌های تسک و بورد؛ تشخیص قصد با مدل است و مجوز و اجرا با سرویس‌های سرور. */
final class ReminderIntent
{
    public const PROMPT = <<<'TXT'
Reminder terminology (apply this distinction before selecting any action or tool):
- Explicit overdue context has priority over the generic word reminder: «یادآوری تأخیر»، «یادآوری تاخیر»، «یادآوری بعد از گذشت زمان»، «بعد از گذشت زمان انجام»، «بعد از موعد»، «بعد از سررسید»، «در صورت تأخیر»، «اگه انجام نشد» and similar phrases mean OVERDUE reminder settings. Recognize common Persian spelling/spacing variants. These requests concern notifications after the existing deadline; never move the main deadline to implement them.
- Without explicit overdue context, «یادآوری»، «یاداوری»، «یادآور»، «تاریخ انجام»، «زمان انجام»، «تاریخ تسک»، «سررسید»، «موعد انجام» and similar words mean the MAIN due_date/due_time. A bare reminder request must not enable, disable or change overdue repetition.
- Examples: «یادآوری رو بذار فردا ساعت ۱۰» and «تاریخ انجام فردا ساعت ۱۰ باشه» => main due_date/due_time only. «دو ساعت دیگه یادآوری کن» => main deadline two hours from NOW, not every two hours. «یادآوری تأخیر هر ۱۰ دقیقه» and «یادآوری بعد از گذشت زمان هر ۲ ساعت» => overdue interval only; preserve the main deadline.
- If both are requested, handle the main deadline and overdue interval as separate explicit changes. If the main date/time cannot be determined from the request plus visible task context, or the overdue number/unit is missing or ambiguous, ask a short Persian clarification. «هر ۲ ساعت یادآوری کن» without any overdue context is not permission to invent overdue repetition; clarify the intended main reminder time versus repetition.
TXT;
}
