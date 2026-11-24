<?php
namespace KarlixLaravel\Services;

/** داده‌های مشترک صفحه و فایل‌های گزارش؛ فقط داده‌های آماده‌شده‌ی مجاز را می‌پذیرد. */
final class AnalyticsReport
{
    public static function rows(array $p, array $a, array $k): array
    {
        $h = static fn(?float $x) => $x === null ? null : round($x, 1);
        $rows = [];
        $rows[] = ['تحلیل پروژه', (string) $p['title']];
        $rows[] = ['تاریخ خروجی', \App\Core\Jalali::format(date('Y-m-d H:i:s'))];
        $rows[] = [];
        $t = $a['totals'];
        $rows[] = ['کل تسک‌ها', 'تأییدشده', 'منتظر تأیید', 'تحویل‌نشده', 'عقب‌افتاده', 'میانگین زمان انجام (ساعت)', 'میانه‌ی زمان انجام (ساعت)', 'تعداد نمونه', 'نرخ برگشت برای اصلاح (٪)', 'امتیاز کلی', 'ارزیابی کلی'];
        $d = $k['durations'];
        $rows[] = [(int) $t['total'], (int) $t['done'], (int) $t['review'], (int) $t['open'] - (int) $t['review'], (int) $t['overdue'], $h($d['a']), $h($d['med']), $d['c'],
            $k['summary']['rework_pct'] ?? '', $k['summary']['rating']['score'] ?? '', $k['summary']['rating']['label']];
        $rows[] = [];
        $rows[] = ['عضو', 'موبایل', 'کل', 'تأییدشده', 'منتظر تأیید', 'تحویل‌نشده', 'عقب‌افتاده', 'تحویل با تأخیر', 'به‌موقع (٪)', 'برگشت برای اصلاح (٪)', 'میانگین زمان تا تحویل (ساعت)', 'میانه (ساعت)', 'تعداد تحویل‌شده', 'امتیاز سرعت', 'ارزیابی'];
        foreach ($k['members'] as $m) {
            $rows[] = [full_name($m), $m['is_orphan'] ? '' : (string) $m['mobile'], $m['total'], $m['done'], $m['waiting'], $m['open'], $m['overdue'], $m['late'], $m['on_time'] ?? '', $m['rework_pct'] ?? '',
                $h($m['avg_hours']), $h($m['median_hours']), $m['rating']['n'] ?? 0, $m['rating']['score'] ?? '', $m['rating']['label']];
        }
        if ($k['reviewers']) {
            $rows[] = [];
            $rows[] = ['تأییدکننده', 'تعداد تأیید', 'میانگین زمان تأیید (ساعت)', 'میانه (ساعت)'];
            foreach ($k['reviewers'] as $rv) {
                $rows[] = [full_name($rv), $rv['count'], $h($rv['avg_hours']), $h($rv['median_hours'])];
            }
        }
        $rows[] = [];
        $rows[] = ['روند ۱۴ روزه', 'ثبت‌شده', 'تحویل‌شده'];
        foreach ($a['trend']['labels'] as $i => $label) $rows[] = [$label, $a['trend']['created'][$i], $a['trend']['done'][$i]];
        $rows[] = [];
        $rows[] = ['اولویت', 'تعداد'];
        foreach (['high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم'] as $key => $label) $rows[] = [$label, $t[$key]];
        $rows[] = [];
        $rows[] = ['لیبل', 'تعداد', 'درصد'];
        foreach ($k['subjects'] as $label) $rows[] = [$label['label'], $label['value'], $label['pct']];
        $rows[] = [];
        $rows[] = ['تحلیل', 'مقدار'];
        $rows[] = ['خلاصه', $k['summary']['text']];
        foreach ($k['summary']['split'] as $key => $pct) $rows[] = [$key, $pct];
        $rows[] = [];
        $rows[] = ['آخرین تسک‌ها', 'مسئول', 'اولویت', 'تکمیل‌شده', 'تاریخ ثبت', 'سررسید'];
        foreach ($a['recent'] as $task) $rows[] = [$task['title'], full_name($task), $task['priority'], (int) $task['is_completed'], $task['created_at'], $task['due_date']];
        return $rows;
    }
}
