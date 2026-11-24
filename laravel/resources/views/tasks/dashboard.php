<?php
/** @var array $d @var array $me @var array $focus @var array $waiting @var array $subjects @var array $projects @var array $assignees */
$s = $d['stats'];
$this->boot['d'] = ['stats' => $s, 'trend' => $d['trend'], 'prio_open' => $d['prio_open']];
$this->scripts[] = 'dashboard';
$time = static fn(array $t) => !empty($t['c']) ? duration_fa((float) $t['a']) : '—';
$med = static fn(array $t) => !empty($t['c']) ? duration_fa((float) $t['med']) : '—';
$nToday = $focus['today_count'];
$nLate = $focus['overdue_count'];
if ($nToday && $nLate) {
    $summary = 'امروز ' . fa_num($nToday) . ' تسک و ' . fa_num($nLate) . ' تسک با تأخیر دارید.';
} elseif ($nToday) {
    $summary = 'امروز ' . fa_num($nToday) . ' تسک دارید.';
} elseif ($nLate) {
    $summary = 'امروز تسکی ندارید، ولی ' . fa_num($nLate) . ' تسک با تأخیر دارید.';
} else {
    $summary = 'امروز تسکی ندارید و هیچ تسکی هم عقب نیفتاده است.';
}
$prioName = ['high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'];
/** یک ردیف تسک «کارهای من» (v7.16، ردیف .tr دمو): عنوان، چیپ وضعیت/سررسید/لیبل/یادداشت؛ پروژه و نقطه‌ی اولویت (کلیک = جزئیات در همین صفحه) */
$taskRow = static function (array $t, string $kind) use ($prioName): string {
    $pr = isset($prioName[$t['priority']]) ? $t['priority'] : 'medium';
    $time = $t['due_time'] ? fa_num(substr((string) $t['due_time'], 0, 5)) : '';
    $hourPast = $kind === 'today' && $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time();
    if ($kind === 'late') {
        $due = '<span class="chip-s is-danger"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' . e(fa_num($t['late_days'] ?? 1)) . ' روز تأخیر</span>';
    } elseif ($kind === 'today') {
        $due = '<span class="chip-s ' . ($hourPast ? 'is-danger' : 'is-warn') . '"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' . ($time !== '' ? ($hourPast ? 'ساعت ' . e($time) . ' گذشته' : 'امروز ' . e($time)) : 'امروز') . '</span>';
    } else {
        $due = '<span class="chip-s"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' . e(jdate($t['due_date'] . ' 00:00:00', false)) . ($time !== '' ? ' ' . e($time) : '') . '</span>';
    }
    $st = \KarlixLaravel\Services\BoardConfig::label(isset($t['project_id']) ? (int) $t['project_id'] : null, (string) ($t['board_status'] ?? ''));
    $lbl = $t['subject_title'] ? '<span class="chip-s is-outline"><span class="dot" data-css="background:' . e((string) $t['subject_color']) . '" aria-hidden="true"></span>' . e($t['subject_title']) . '</span>' : '';
    $notes = (int) ($t['notes_count'] ?? 0) ? '<span class="tr-cnt"><i class="fa-regular fa-comment" aria-hidden="true"></i>' . e(fa_num((int) $t['notes_count'])) . '</span>' : '';
    return '<a class="dash-task tr p-' . e($pr) . '" data-prio="' . e($pr) . '" href="' . e(url('/tasks?task=' . $t['share_token'])) . '" data-open-task="' . e(pid('tasks', $t)) . '">'
        . '<span class="tr-main"><span class="tr-title"><span class="sr-only">اولویت ' . e($prioName[$pr]) . ': </span>' . e($t['title']) . '</span>'
        . '<span class="tr-meta">' . ($st !== '' ? '<span class="chip-s"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>' . e($st) . '</span>' : '') . $due . $lbl . $notes . '</span></span>'
        . ($t['project_title'] ? '<span class="tr-proj"><span class="proj-ic sm" aria-hidden="true">' . e(initial($t['project_title'])) . '</span><span class="truncate">' . e($t['project_title']) . '</span></span>' : '')
        . '<span class="dot p-' . e($pr) . '" title="اولویت ' . e($prioName[$pr]) . '" aria-hidden="true"></span></a>';
};
// سلام بر اساس ساعت تهران (v5.6)
$nowTeh = new \DateTime('now', new \DateTimeZone('Asia/Tehran'));
$hr = (int) $nowTeh->format('G');
$greet = $hr < 11 ? 'صبح بخیر' : ($hr < 16 ? 'ظهر بخیر' : ($hr < 20 ? 'عصر بخیر' : 'شب بخیر'));
$mix = $focus['mix'];
$mixTotal = $mix['high'] + $mix['medium'] + $mix['low'];
$sg = $focus['suggest'];
$w = $waiting;
$wCount = count($w['mentions']) + count($w['chats']) + count($w['colleagues']) + count($w['invites']) + count($w['review']) + count($w['rework']);
?>
<?php
$todayPast = count(array_filter($focus['today'], static fn($t) => $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time()));
$oldest = $focus['overdue'] ? max(array_map(static fn($t) => (int) ($t['late_days'] ?? 0), $focus['overdue'])) : 0;
$nSoon = $focus['upcoming_count'];
$tabs = [['today', 'امروز', $nToday, $focus['today'], 'برای امروز تسکی ندارید.', '/tasks?when=today'],
    ['late', 'عقب‌افتاده', $nLate, $focus['overdue'], 'تسک عقب‌افتاده‌ای ندارید.', '/tasks?when=overdue'],
    ['soon', '۷ روز آینده', $nSoon, $focus['upcoming'], 'در هفت روز آینده سررسیدی ندارید.', '/tasks?status=open']];
$tabOn = $nToday ? 'today' : ($nLate ? 'late' : 'today');
?>
<?php /* v7.16 (دموی UI): سربرگ سلام، چهار کارت آمار، «کارهای من» با تب امروز/عقب‌افتاده/۷ روز آینده، ستون کناری پیشنهاد/منتظر اقدام/ترکیب/تیم/آمار */ ?>
<div class="page-head dash-head">
    <div class="ph-text">
        <time class="dash-date" datetime="<?= e($nowTeh->format('Y-m-d')) ?>"><?= e(jdate($nowTeh->format('Y-m-d H:i:s'), false, true)) ?></time>
        <h2><?= e($greet) ?>، <?= e(($me['first_name'] ?? '') !== '' ? $me['first_name'] : full_name($me)) ?></h2>
        <p><?= e($summary) ?></p>
    </div>
    <div class="ph-actions"><button type="button" class="btn btn-primary" id="btn-new-task" data-create-default><i class="fa-solid fa-plus" aria-hidden="true"></i> تسک جدید</button></div>
</div>

<div data-live="dash-stats" class="grid grid-4 dash-stats">
    <a class="card stat" href="<?= e(url('/tasks?when=today')) ?>"><span class="st-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i>تسک‌های امروز</span><span class="st-value"><?= e(fa_num($nToday)) ?></span><span class="st-meta"><?= $todayPast ? e(fa_num($todayPast)) . ' مورد ساعتش گذشته' : 'برنامه‌ی امروز' ?></span></a>
    <a class="card stat" href="<?= e(url('/tasks?when=overdue')) ?>"><span class="st-label"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>عقب‌افتاده</span><span class="st-value<?= $nLate ? ' is-danger' : '' ?>"><?= e(fa_num($nLate)) ?></span><span class="st-meta"><?= $oldest ? 'قدیمی‌ترین: ' . e(fa_num($oldest)) . ' روز' : 'هیچ تسکی عقب نیفتاده' ?></span></a>
    <a class="card stat" href="#dash-wait"><span class="st-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>منتظر اقدام شما</span><span class="st-value"><?= e(fa_num($wCount)) ?></span><span class="st-meta"><?= e(fa_num(count($w['review']))) ?> بررسی · <?= e(fa_num(count($w['rework']))) ?> اصلاح</span></a>
    <a class="card stat" href="<?= e(url('/tasks?scope=mine&status=open')) ?>"><span class="st-label"><i class="fa-solid fa-person-running" aria-hidden="true"></i>در جریان</span><span class="st-value"><?= e(fa_num($s['pending'])) ?></span><span class="st-meta">از <?= e(fa_num($s['total'])) ?> تسک من<?= $s['waiting'] > 0 ? ' · ' . e(fa_num($s['waiting'])) . ' منتظر تأیید' : '' ?></span></a>
</div>

<div class="dash-grid dash-main">
<div class="dash-col">
    <section class="card is-flush dash-focus" aria-labelledby="dash-focus-h" data-live="dash-focus">
        <div class="card-head is-bordered"><h3 id="dash-focus-h"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> کارهای من</h3>
            <div class="tabs dash-filter" role="group" aria-label="فیلتر بر اساس اولویت">
                <button type="button" class="tab dash-fbtn" data-dash-prio="all" aria-pressed="true">همه</button>
                <button type="button" class="tab dash-fbtn" data-dash-prio="high" aria-pressed="false"><span class="dot p-high" aria-hidden="true"></span>بالا</button>
                <button type="button" class="tab dash-fbtn" data-dash-prio="medium" aria-pressed="false"><span class="dot p-medium" aria-hidden="true"></span>متوسط</button>
                <button type="button" class="tab dash-fbtn" data-dash-prio="low" aria-pressed="false"><span class="dot p-low" aria-hidden="true"></span>پایین</button>
            </div>
            <span class="sr-only" aria-live="polite" id="dash-filter-live"></span>
        </div>
        <div class="utabs" role="tablist" aria-label="بازه‌ی زمانی">
            <?php foreach ($tabs as [$k, $lbl, $n]): ?><button type="button" role="tab" class="utab" id="dash-tab-<?= $k ?>" aria-controls="dash-sec-<?= $k ?>" aria-selected="<?= $k === $tabOn ? 'true' : 'false' ?>" data-dash-tab="<?= $k ?>"<?= $k === $tabOn ? '' : ' tabindex="-1"' ?>><?= e($lbl) ?> <span class="cnt"><?= e(fa_num($n)) ?></span></button><?php endforeach; ?>
        </div>
        <?php foreach ($tabs as [$k, $lbl, $n, $rows, $none, $more]): ?>
        <div class="dash-sec-body" id="dash-sec-<?= $k ?>" role="tabpanel" aria-labelledby="dash-tab-<?= $k ?>"<?= $k === $tabOn ? '' : ' hidden' ?>>
            <?php if ($rows): ?><div class="dash-tasks"><?php foreach ($rows as $t) { echo $taskRow($t, $k); } ?></div><div class="empty sm dash-empty" hidden>موردی با این اولویت نیست.</div>
                <?php if ($n > count($rows)): ?><a class="dash-more" href="<?= e(url($more)) ?>">مشاهده‌ی همه (<?= e(fa_num($n)) ?>)</a><?php endif; ?>
            <?php else: ?><div class="empty sm"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><b><?= e($none) ?></b></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>

<div class="grid dash-charts">
    <?php // DATA-017: عنوان/زیرعنوان دقیق هر نمودار ?>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> تسک‌های تازه‌ی واگذارشده به من</h3><span class="xs faint">۷ روز اخیر، بر اساس روز ثبت</span></div><div class="chart" id="ch-trend"></div>
        <p class="hint">تعداد تسک‌هایی که هر روز ثبت شده و مسئولش من هستم (بار کاری تازه، نه کار انجام‌شده).</p></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bullseye"></i> نرخ تکمیل</h3><span class="xs faint">تسک‌هایی که مسئولشان من هستم</span></div>
        <div id="ch-ratio"></div>
        <?php if ($d['top_subject'] !== null): // فقط وقتی دست‌کم یک تسک لیبل دارد ?><div class="mt-2 small muted center">پرتکرارترین لیبل: <b class="dc-13fd80 text-green"><?= e($d['top_subject']) ?></b> · <?= e(fa_num($d['top_subject_count'])) ?> تسک</div><?php endif; ?></div>
</div>

<div class="grid grid-2 dash-charts">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> اولویت تسک‌های در جریان</h3><span class="xs faint">تحویل‌نشده</span></div><div class="chart" id="ch-prio"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-people-arrows"></i> گردش کار</h3></div>
        <div class="list">
            <?php // «باز از کل»؛ هر ردیف به فهرست فیلترشده‌ی خودش می‌رود ?>
            <a class="flex between dash-flow-row" href="<?= e(url('/tasks?scope=others&status=open')) ?>" title="تسک‌های باز واگذارشده به دیگران"><span class="muted">ساخته‌شده توسط من برای دیگران</span><b><?= e(fa_num($d['by_me_open'])) ?> باز <span class="faint xs">از <?= e(fa_num($d['by_me'])) ?></span></b></a>
            <a class="flex between dash-flow-row" href="<?= e(url('/tasks?scope=mine&status=open')) ?>" title="وظایف باز من"><span class="muted">واگذارشده به من از دیگران</span><b><?= e(fa_num($d['to_me_open'])) ?> باز <span class="faint xs">از <?= e(fa_num($d['to_me'])) ?></span></b></a>
            <a class="flex between dash-flow-row" href="<?= e(url('/notes')) ?>"><span class="muted">یادداشت‌ها</span><b><?= e(fa_num($d['texts'])) ?></b></a>
        </div></div>
    <?php // DATA-004: همان تعریف تحلیل پروژه = ایجاد تا تحویل، فقط تسک‌هایی که مسئولشان من هستم؛ با میانه و تعداد نمونه ?>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-stopwatch"></i> میانگین زمان انجام</h3><span class="xs faint">از ثبت تا تحویل</span></div>
        <div class="list">
            <div class="flex between"><span class="muted">میانگین</span><b><?= e($time($d['task_time'])) ?></b></div>
            <div class="flex between"><span class="muted">میانه</span><b><?= e($med($d['task_time'])) ?></b></div>
            <div class="flex between"><span class="muted">تعداد تسک</span><b><?= e(fa_num((int) $d['task_time']['c'])) ?></b></div>
        </div>
        <p class="hint">فقط تسک‌هایی که مسئولشان من هستم و تحویل داده‌ام (منتظر تأیید یا تأییدشده)؛ زمان انتظار تأیید سرپرست حساب نمی‌شود.</p></div>
</div>


</div>
<div class="dash-side" data-live="dash-side">
    <?php if ($sg): $sp = isset($prioName[$sg['priority']]) ? $sg['priority'] : 'medium'; ?>
    <aside class="card dash-suggest" aria-label="پیشنهاد شروع">
        <div class="dash-suggest-k"><i class="fa-solid fa-hand-sparkles" aria-hidden="true"></i> پیشنهاد برای شروع</div>
        <button type="button" class="dash-suggest-title" data-open-task="<?= e(pid('tasks', $sg)) ?>"><?= e($sg['title']) ?></button>
        <div class="tr-meta"><span class="chip-s is-<?= $sp === 'high' ? 'danger' : ($sp === 'medium' ? 'warn' : 'info') ?>"><span class="dot p-<?= e($sp) ?>" aria-hidden="true"></span><?= e($prioName[$sp]) ?></span><?php if ($sg['due_date'] < date('Y-m-d')): ?><span class="chip-s is-danger"><i class="fa-regular fa-calendar" aria-hidden="true"></i>عقب‌افتاده</span><?php endif; ?><?php if ($sg['project_title']): ?><span class="faint xs"><?= e($sg['project_title']) ?></span><?php endif; ?></div>
    </aside>
    <?php endif; ?>
    <div class="card dash-wait" id="dash-wait">
        <div class="card-head"><h3><i class="fa-solid fa-hourglass-half"></i> منتظر اقدام <?php if ($wCount): ?><span class="badge yellow" data-ri-badge><?= e(fa_num($wCount)) ?></span><?php endif; ?></h3></div>
        <?php if (!$wCount && !$w['reviewer']): ?><div class="empty small"><i class="fa-regular fa-circle-check"></i><p>چیزی منتظر شما نیست.</p></div><?php endif; ?>

        <?php // صندوق بررسی سرپرست (v7.9): نوار خلاصه + ۳ موردِ بیشترین انتظار؛ بقیه با «نمایش همه» و کامل در مودال صندوق بررسی
        $rs = $w['review_sum']; $rsItems = $rs['items'];
        if ($w['review'] || $w['reviewer']): ?>
        <div class="dash-sec ri-sec" id="ri-sec">
            <div class="ri-sum">
                <span class="ri-sum-ic" aria-hidden="true"><i class="fa-solid <?= $rs['count'] ? 'fa-inbox' : 'fa-circle-check' ?>"></i></span>
                <div class="grow min0">
                    <?php if ($rs['count']): ?>
                    <b class="ri-sum-t"><span class="ri-num"><?= e(fa_num($rs['count'])) ?></span> تحویل منتظر شماست</b>
                    <div class="xs faint ri-sum-sub">قدیمی‌ترین: <span class="ri-num"><?= e($rs['oldest_label']) ?></span><?php if ($rs['over_sla_count']): ?> <span class="ri-warn">• <span class="ri-num"><?= e(fa_num($rs['over_sla_count'])) ?></span> مورد بیش از مهلت</span><?php endif; ?></div>
                    <?php else: ?>
                    <b class="ri-sum-t ri-ok">صندوق بررسی خالی است</b>
                    <?php endif; ?>
                </div>
                <?php if ($rs['count']): ?><button type="button" class="btn btn-sm btn-primary" data-ri-open aria-haspopup="dialog"><i class="fa-solid fa-inbox" aria-hidden="true"></i> ورود به صندوق بررسی</button><?php endif; ?>
            </div>
            <?php if ($rs['count']): ?>
            <div class="list"><?php foreach ($w['review'] as $ri => $t): ?>
                <div class="item dash-wait-item dash-review-item<?= $ri >= 3 ? ' ri-extra' : '' ?>"<?= $ri >= 3 ? ' hidden' : '' ?>>
                    <a class="flex items-center gap-2 grow min0" href="<?= e(url('/tasks?task=' . ($t['share_token'] ?: ''))) ?>" data-open-task="<?= e(pid('tasks', $t)) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($t), 'src' => avatar_url($t['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b>«<?= e($t['title']) ?>»</b> منتظر تأیید شماست</div>
                        <div class="xs faint truncate">مسئول: <?= e(full_name($t)) ?> • <?= $t['project_title'] ? '<i class="fa-solid fa-diagram-project"></i> ' . e($t['project_title']) . ' • ' : '' ?>انتظار: <span class="ri-num<?= $rsItems[$ri]['over_sla'] ? ' ri-warn' : '' ?>"><?= e($rsItems[$ri]['wait_label']) ?></span></div>
                        <?php // پیش‌نمایش کار پیش از تأیید: آخرین یادداشت مسئول، پیوست‌ها و تحویل نسبت به سررسید (v5.4)
                        $dl = $t['delivered_at'] ? substr((string) $t['delivered_at'], 0, 10) : date('Y-m-d');
                        $lateDays = $t['due_date'] && $dl > $t['due_date'] ? (int) round((strtotime($dl) - strtotime((string) $t['due_date'])) / 86400) : 0; ?>
                        <div class="dash-preview xs">
                            <?php if (trim((string) $t['last_note']) !== ''): ?><span class="dash-pv-note" title="<?= e((string) $t['last_note']) ?>"><i class="fa-regular fa-comment"></i> «<?= e(mb_strimwidth(preg_replace('/\s+/u', ' ', (string) $t['last_note']), 0, 90, '…')) ?>»</span><?php else: ?><span class="faint"><i class="fa-regular fa-comment"></i> یادداشتی از مسئول نیست</span><?php endif; ?>
                            <span class="<?= (int) $t['files_count'] ? '' : 'faint' ?>"><i class="fa-solid fa-paperclip"></i> <?= (int) $t['files_count'] ? e(fa_num($t['files_count'])) . ' پیوست' : 'بدون پیوست' ?></span>
                            <?php if ($t['due_date']): ?><span class="<?= $lateDays ? 'prio-high' : 'dash-pv-ok' ?>"><i class="fa-regular fa-calendar"></i> <?= $lateDays ? e(fa_num($lateDays)) . ' روز بعد از سررسید تحویل شد' : 'به‌موقع تحویل شد' ?></span><?php else: ?><span class="faint"><i class="fa-regular fa-calendar"></i> بدون سررسید</span><?php endif; ?>
                        </div></div></a>
                    <?php // تأیید یا برگرداندن بدون باز کردن تسک ?>
                    <div class="flex gap-1 dash-review-acts">
                        <button type="button" class="btn btn-xs btn-primary" data-post="<?= e(url('/tasks/' . pid('tasks', $t) . '/status')) ?>" data-data='{"status":"done"}' title="تأیید و بستن"><i class="fa-solid fa-check-double"></i> تأیید</button>
                        <button type="button" class="btn btn-xs" data-dash-rework="<?= e(pid('tasks', $t)) ?>" title="برگرداندن برای اصلاح"><i class="fa-solid fa-rotate-left"></i> اصلاح</button>
                    </div></div>
            <?php endforeach; ?></div>
            <?php if ($rs['count'] > 3): ?><button type="button" class="ri-more-link" data-ri-more aria-expanded="false" data-ri-n="<?= e(fa_num($rs['count'] - 3)) ?>">نمایش همه (<?= e(fa_num($rs['count'] - 3)) ?> مورد دیگر)</button><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($w['rework']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="dc-483f44 fa-solid fa-rotate-left"></i> برگشته برای اصلاح (بازبینی مسئول)</b></div>
            <div class="list"><?php foreach ($w['rework'] as $t): ?>
                <a class="item dash-wait-item" href="<?= e(url('/tasks?task=' . ($t['share_token'] ?: ''))) ?>" data-open-task="<?= e(pid('tasks', $t)) ?>">
                    <span class="avatar sm sq dash-rework-ico"><i class="fa-solid fa-rotate-left"></i></span>
                    <div class="grow min0"><div class="small truncate">«<?= e($t['title']) ?>»<?= $t['blocked_reason'] ? ' — ' . e($t['blocked_reason']) : '' ?></div>
                        <div class="xs faint truncate"><?= $t['project_title'] ? '<i class="fa-solid fa-diagram-project"></i> ' . e($t['project_title']) . ' • ' : '' ?><?= e(time_ago($t['board_moved_at'] ?? '')) ?></div></div></a>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['mentions']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-at"></i> منشن‌ها</b></div>
            <div class="list"><?php foreach ($w['mentions'] as $m): $an = full_name($m); ?>
                <a class="item dash-wait-item" href="<?= e(url('/tasks?task=' . ($m['share_token'] ?: ''))) ?>" data-open-task="<?= e(pid('tasks', $m['task_id'])) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e($an) ?></b> شما را در «<?= e($m['title']) ?>» منشن کرد</div><div class="xs faint"><?= e(time_ago($m['created_at'])) ?></div></div></a>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['chats']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-regular fa-comments"></i> پیام‌های خوانده‌نشده</b></div>
            <div class="list"><?php foreach ($w['chats'] as $c): $cn = full_name($c); ?>
                <a class="item dash-wait-item" href="<?= e(url('/chat?user=' . pid('users', $c['sender_id']))) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($c), 'src' => avatar_url($c['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e($cn) ?></b></div><div class="xs faint"><?= e(time_ago($c['last_at'])) ?></div></div>
                    <span class="badge-count"><?= e(fa_num($c['cnt'])) ?></span></a>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['colleagues']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-user-plus"></i> درخواست همکاری</b></div>
            <div class="list"><?php foreach ($w['colleagues'] as $r): ?>
                <div class="item" id="dash-cr-<?= e(pid('colleague_requests', $r)) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($r), 'src' => avatar_url($r['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e(full_name($r)) ?></b> می‌خواهد همکار شما شود</div><div class="xs faint"><?= e(time_ago($r['created_at'])) ?></div></div>
                    <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/colleagues/requests/' . pid('colleague_requests', $r) . '/accept')) ?>" data-after="reload">پذیرفتن</button><button class="btn btn-sm" data-post="<?= e(url('/colleagues/requests/' . pid('colleague_requests', $r) . '/reject')) ?>" data-after="reload">رد</button></div></div>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['invites']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-envelope-open-text"></i> دعوت به پروژه</b></div>
            <div class="list"><?php foreach ($w['invites'] as $r): ?>
                <div class="item">
                    <span class="avatar sm sq"><?php if ($r['profile_image']): ?><img loading="lazy" decoding="async" src="<?= e(project_image_url($r['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial($r['title'])) ?><?php endif; ?></span>
                    <div class="grow min0"><div class="small truncate">عضویت در <b>«<?= e($r['title']) ?>»</b></div><div class="xs faint">دعوت از <?= e(full_name($r)) ?> • <?= e(time_ago($r['created_at'])) ?></div></div>
                    <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/projects/requests/' . pid('project_join_requests', $r) . '/respond')) ?>" data-data='{"action":"accept"}' data-after="reload">پذیرفتن</button><button class="btn btn-sm" data-post="<?= e(url('/projects/requests/' . pid('project_join_requests', $r) . '/respond')) ?>" data-data='{"action":"reject"}' data-after="reload">رد</button></div></div>
            <?php endforeach; ?></div></div>
        <?php endif; ?>
    </div>
    <?php $mixLabel = fa_num($mix['high']) . ' تسک اولویت بالا، ' . fa_num($mix['medium']) . ' تسک اولویت متوسط، ' . fa_num($mix['low']) . ' تسک اولویت پایین'; ?>
    <section class="card dash-mix" aria-labelledby="dash-mix-h">
        <div class="card-head"><h3 id="dash-mix-h"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> ترکیب اولویت‌ها</h3><span class="xs faint">امروز + عقب‌افتاده</span></div>
        <div class="dash-mix-bar<?= $mixTotal ? '' : ' is-empty' ?>" role="img" aria-label="<?= e($mixTotal ? $mixLabel : 'تسکی برای امروز یا عقب‌افتاده نیست') ?>">
            <?php foreach (['high', 'medium', 'low'] as $k): if ($mix[$k]): ?><span class="dash-mix-seg p-<?= $k ?>" data-css="flex:<?= (int) $mix[$k] ?> 1 0"></span><?php endif; endforeach; ?>
        </div>
        <?php if (!$mixTotal): ?><div class="xs faint mt-1">تسکی برای امروز یا عقب‌افتاده ندارید.</div><?php endif; ?>
        <ul class="dash-mix-legend">
            <?php foreach (['high', 'medium', 'low'] as $k): ?><li class="p-<?= $k ?>"><span class="dash-mix-sq" aria-hidden="true"></span>اولویت <?= e($prioName[$k]) ?> <b><?= e(fa_num($mix[$k])) ?></b></li><?php endforeach; ?>
        </ul>
    </section>
<?php if (!empty($team)): // «تیم من» (v5.4): وضعیت تیم برای کسی که در پروژه دسترسی تحلیل دارد ?>
<div class="card dash-team">
    <div class="card-head"><h3><i class="fa-solid fa-people-group"></i> تیم من</h3><span class="xs faint">پروژه‌هایی که تحلیلشان را می‌بینید؛ روی هر عدد بزنید تا همان فهرست باز شود</span></div>
    <div class="dash-team-grid">
    <?php foreach ($team as $tm): $p = $tm['project']; $c = $tm['c']; $pp = pid('projects', $p);
        $tl = static fn(array $q) => url('/tasks') . '?' . http_build_query(['scope' => 'project', 'project' => $pp] + $q); ?>
        <div class="dash-team-proj">
            <div class="flex between items-center gap-1"><a class="bold truncate" href="<?= e(url('/projects/' . $pp . '/analytics')) ?>" title="تحلیل پروژه"><i class="fa-solid fa-diagram-project"></i> <?= e($p['title']) ?></a><span class="xs faint nowrap"><?= e(fa_num($tm['members'])) ?> نفر • <?= e(fa_num($c['open'])) ?> باز</span></div>
            <div class="dash-team-nums">
                <a class="<?= $c['overdue'] ? 'is-bad' : '' ?>" href="<?= e($tl(['status' => 'open', 'when' => 'overdue'])) ?>"><b><?= e(fa_num($c['overdue'])) ?></b><span>عقب‌افتاده</span></a>
                <a class="<?= $c['review'] ? 'is-warn' : '' ?>" href="<?= e(url('/board?project=' . $pp)) ?>" title="ستون «بررسی سرپرست» در بورد همین پروژه"><b><?= e(fa_num($c['review'])) ?></b><span>منتظر تأیید</span></a>
                <a href="<?= e(url('/board?project=' . $pp)) ?>" title="ستون «بازبینی مسئول» در بورد همین پروژه"><b><?= e(fa_num($c['rework'])) ?></b><span>برگشت برای اصلاح</span></a>
                <span title="تسک‌های مسدودشده‌ی این پروژه"><b><?= e(fa_num($c['blocked'])) ?></b><span>مسدود</span></span>
            </div>
            <?php if ($tm['top']): ?><div class="dash-team-top xs"><span class="faint">بیشترین عقب‌افتاده:</span>
                <?php foreach ($tm['top'] as $u): ?><a href="<?= e($tl(['status' => 'open', 'when' => 'overdue', 'assignee' => pid('users', $u)])) ?>"><?= e(full_name($u)) ?> <b><?= e(fa_num($u['n'])) ?></b></a><?php endforeach; ?></div>
            <?php else: ?><div class="dash-team-top xs dash-pv-ok"><i class="fa-solid fa-circle-check"></i> هیچ عضوی کار عقب‌افتاده ندارد.</div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
    <section class="card dash-kv" aria-labelledby="dash-kv-h">
        <div class="card-head"><h3 id="dash-kv-h">آمار کلی من</h3></div>
        <dl class="kv">
            <dt><a href="<?= e(url('/tasks?scope=mine&status=all')) ?>">کل تسک‌های من</a></dt><dd><?= e(fa_num($s['total'])) ?></dd>
            <dt><a href="<?= e(url('/tasks?scope=mine&status=done')) ?>">تأییدشده</a></dt><dd><?= e(fa_num($s['completed'])) ?></dd>
            <dt><a href="<?= e(url('/tasks?scope=mine&status=open')) ?>">در جریان (تحویل‌نشده)</a></dt><dd><?= e(fa_num($s['pending'])) ?></dd>
            <dt><a href="<?= e(url('/tasks?scope=mine&status=open&prio=high')) ?>">اولویت زیاد (در جریان)</a></dt><dd><?= e(fa_num($d['prio_open']['high'])) ?></dd>
            <dt>میانگین زمان انجام</dt><dd><?= e($time($d['task_time'])) ?></dd>
            <dt><a href="<?= e(url('/notes')) ?>">یادداشت‌ها</a></dt><dd><?= e(fa_num($d['texts'])) ?></dd>
        </dl>
    </section>
</div>
</div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => null, 'context' => 'dashboard']); ?>
<?php if ($w['review'] || $w['reviewer']) { $this->partial('review_inbox'); } ?>
