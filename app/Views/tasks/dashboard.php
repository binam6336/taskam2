<?php
/** @var array $d @var array $me @var array $focus @var array $waiting @var array $subjects @var array $projects @var array $assignees */
$s = $d['stats'];
$this->boot['d'] = ['stats' => $s, 'trend' => $d['trend'], 'calls' => $d['calls'], 'prio_open' => $d['prio_open']];
$this->scripts[] = 'dashboard';
$time = static fn(array $t) => !empty($t['c']) ? duration_fa((float) $t['a']) : '—';
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
/** یک ردیف تسک در باکس امروز/عقب‌افتاده (کلیک = جزئیات تسک در همین صفحه) */
$taskRow = static function (array $t, bool $late) use ($prioName): string {
    $due = $late ? jdate($t['due_date'] . ' 00:00:00', false) : '';
    $time = $t['due_time'] ? fa_num(substr((string) $t['due_time'], 0, 5)) : '';
    // امروز با ساعت گذشته: در «امروز» می‌ماند (عقب‌افتاده فقط روزهای قبل) و برچسب قرمز «ساعت گذشته» می‌گیرد
    $hourPast = !$late && $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time();
    $pr = isset($prioName[$t['priority']]) ? $t['priority'] : 'medium';
    return '<a class="dash-task p-' . e($pr) . '" data-prio="' . e($pr) . '" title="' . e($t['title']) . '" href="' . e(url('/tasks?task=' . $t['share_token'])) . '" data-open-task="' . e(pid('tasks', $t)) . '">'
        . '<span class="dash-task-dot" title="اولویت ' . e($prioName[$pr]) . '"></span>'
        . '<span class="grow truncate"><span class="sr-only">اولویت ' . e($prioName[$pr]) . ': </span>' . e($t['title']) . '</span>'
        . ($t['project_title'] ? '<span class="dash-task-proj truncate"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> ' . e($t['project_title']) . '</span>' : '')
        . ($late && !empty($t['late_days']) ? '<span class="dash-late-badge">' . e(fa_num($t['late_days'])) . ' روز تأخیر</span>' : '')
        . '<span class="dash-task-due' . ($late || $hourPast ? ' late' : '') . '">' . e(trim($due . ' ' . $time)) . ($hourPast ? ' <span class="hour-past">ساعت گذشته</span>' : '') . '</span></a>';
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
<div data-live="dash-top">
<div class="dash-hello mb-2">
    <div class="dash-hello-main">
        <time class="dash-date" datetime="<?= e($nowTeh->format('Y-m-d')) ?>"><?= e(jdate($nowTeh->format('Y-m-d H:i:s'), false, true)) ?></time>
        <h2><?= e($greet) ?> <?= e(($me['first_name'] ?? '') !== '' ? $me['first_name'] : full_name($me)) ?> <span aria-hidden="true">👋</span></h2>
        <p><?= e($summary) ?></p>
        <nav class="dash-chips" aria-label="خلاصه و پرش به بخش‌ها">
            <button type="button" class="dash-chip" data-dash-jump="dash-sec-today"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> <?= e(fa_num($nToday)) ?> تسک امروز</button>
            <button type="button" class="dash-chip is-danger" data-dash-jump="dash-sec-late"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= e(fa_num($nLate)) ?> عقب‌افتاده</button>
            <button type="button" class="dash-chip is-warn" data-dash-jump="dash-wait"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> <?= e(fa_num($wCount)) ?> منتظر اقدام شما</button>
        </nav>
    </div>
    <?php if ($sg): $sp = isset($prioName[$sg['priority']]) ? $sg['priority'] : 'medium'; ?>
    <aside class="dash-suggest" aria-label="پیشنهاد شروع">
        <div class="xs muted"><i class="fa-solid fa-play" aria-hidden="true"></i> پیشنهاد برای شروع</div>
        <button type="button" class="dash-suggest-title" data-open-task="<?= e(pid('tasks', $sg)) ?>"><?= e($sg['title']) ?></button>
        <div class="xs muted"><?= $sg['project_title'] ? e($sg['project_title']) . ' · ' : '' ?>اولویت <?= e($prioName[$sp]) ?><?= $sg['due_date'] < date('Y-m-d') ? ' · عقب‌افتاده' : '' ?> <span aria-hidden="true">←</span></div>
    </aside>
    <?php endif; ?>
</div>

<div class="grid grid-2 mb-2 dash-main">
    <section class="card dash-focus" aria-labelledby="dash-focus-h">
        <div class="card-head dash-focus-head"><h3 id="dash-focus-h"><i class="fa-solid fa-bolt" aria-hidden="true"></i> کارهای مهم</h3>
            <div class="dash-filter" role="group" aria-label="فیلتر بر اساس اولویت">
                <button type="button" class="dash-fbtn" data-dash-prio="all" aria-pressed="true">همه</button>
                <button type="button" class="dash-fbtn p-high" data-dash-prio="high" aria-pressed="false"><span class="dash-fdot" aria-hidden="true"></span>بالا</button>
                <button type="button" class="dash-fbtn p-medium" data-dash-prio="medium" aria-pressed="false"><span class="dash-fdot" aria-hidden="true"></span>متوسط</button>
                <button type="button" class="dash-fbtn p-low" data-dash-prio="low" aria-pressed="false"><span class="dash-fdot" aria-hidden="true"></span>پایین</button>
            </div>
            <span class="sr-only" aria-live="polite" id="dash-filter-live"></span>
        </div>
        <div class="dash-sec" id="dash-sec-today">
            <div class="dash-sec-head"><button type="button" class="dash-sec-toggle" aria-expanded="true" aria-controls="dash-body-today" data-dash-sec="today"><i class="fa-solid fa-chevron-down dash-chev" aria-hidden="true"></i><b><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> تسک‌های امروز <span class="badge"><?= e(fa_num($nToday)) ?></span></b></button>
                <?php if ($nToday > count($focus['today'])): ?><a class="xs" href="<?= e(url('/tasks?when=today')) ?>">همه</a><?php endif; ?></div>
            <div id="dash-body-today" class="dash-sec-body">
            <?php if ($focus['today']): ?><div class="dash-tasks"><?php foreach ($focus['today'] as $t) { echo $taskRow($t, false); } ?></div><div class="dash-empty" hidden>موردی با این اولویت نیست 🎉</div>
            <?php else: ?><div class="faint small dash-none">برای امروز تسکی ندارید.</div><?php endif; ?>
            </div>
        </div>
        <div class="dash-sec" id="dash-sec-late">
            <div class="dash-sec-head"><button type="button" class="dash-sec-toggle" aria-expanded="true" aria-controls="dash-body-late" data-dash-sec="late"><i class="fa-solid fa-chevron-down dash-chev" aria-hidden="true"></i><b class="text-danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> تسک‌های عقب‌افتاده <span class="badge red"><?= e(fa_num($nLate)) ?></span></b></button>
                <?php if ($nLate > count($focus['overdue'])): ?><a class="xs" href="<?= e(url('/tasks?when=overdue')) ?>">همه</a><?php endif; ?></div>
            <div id="dash-body-late" class="dash-sec-body">
            <?php if ($focus['overdue']): ?><div class="dash-tasks"><?php foreach ($focus['overdue'] as $t) { echo $taskRow($t, true); } ?></div><div class="dash-empty" hidden>موردی با این اولویت نیست 🎉</div>
            <?php else: ?><div class="faint small dash-none">تسک عقب‌افتاده‌ای ندارید. 👌</div><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="dash-side">
    <div class="card dash-wait" id="dash-wait">
        <div class="card-head"><h3><i class="fa-solid fa-hourglass-half"></i> منتظر اقدام <?php if ($wCount): ?><span class="badge yellow"><?= e(fa_num($wCount)) ?></span><?php endif; ?></h3></div>
        <?php if (!$wCount): ?><div class="empty small"><i class="fa-regular fa-circle-check"></i><p>چیزی منتظر شما نیست.</p></div><?php endif; ?>

        <?php if ($w['review']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-magnifying-glass" data-css="color:#a78bfa"></i> منتظر بررسی سرپرست</b></div>
            <div class="list"><?php foreach ($w['review'] as $t): ?>
                <div class="item dash-wait-item dash-review-item">
                    <a class="flex items-center gap-2 grow min0" href="<?= e(url('/tasks?task=' . ($t['share_token'] ?: ''))) ?>" data-open-task="<?= e(pid('tasks', $t)) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($t), 'src' => avatar_url($t['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b>«<?= e($t['title']) ?>»</b> منتظر تأیید شماست</div>
                        <div class="xs faint truncate">مسئول: <?= e(full_name($t)) ?> • <?= $t['project_title'] ? '<i class="fa-solid fa-diagram-project"></i> ' . e($t['project_title']) . ' • ' : '' ?><?= e(time_ago($t['delivered_at'] ?? $t['board_moved_at'] ?? '')) ?></div>
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
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['rework']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-rotate-left" data-css="color:#fb923c"></i> برگشته برای اصلاح (بازبینی مسئول)</b></div>
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
                    <span class="avatar sm sq"><?php if ($r['profile_image']): ?><img src="<?= e(project_image_url($r['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial($r['title'])) ?><?php endif; ?></span>
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
        <div class="dash-keys xs muted">
            <div><kbd>N</kbd> ثبت سریع تسک</div>
            <div><kbd>↑</kbd> <kbd>↓</kbd> جابه‌جایی بین تسک‌ها · <kbd>Enter</kbd> باز کردن</div>
        </div>
    </section>
    </div>
</div>
</div>
<?php if (!empty($team)): // «تیم من» (v5.4): وضعیت تیم برای کسی که در پروژه دسترسی تحلیل دارد ?>
<div class="card mb-2 dash-team">
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
<div class="grid grid-4 mb-2">
    <a class="card stat" href="<?= e(url('/tasks?scope=mine&status=all')) ?>" title="نمایش همه‌ی تسک‌های من"><div class="ico"><i class="fa-solid fa-list-check"></i></div><div><div class="num"><?= e(fa_num($s['total'])) ?></div><div class="lbl">کل تسک‌های من</div></div></a>
    <a class="card stat" href="<?= e(url('/tasks?scope=mine&status=done')) ?>" title="نمایش تسک‌های انجام‌شده"><div class="ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="num"><?= e(fa_num($s['completed'])) ?></div><div class="lbl">انجام‌شده</div></div></a>
    <a class="card stat" href="<?= e(url('/tasks?scope=mine&status=open')) ?>" title="نمایش تسک‌های در جریان"><div class="ico warn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="num"><?= e(fa_num($s['pending'])) ?></div><div class="lbl">در جریان</div></div></a>
    <?php // فقط تسک‌های باز با اولویت زیاد (v5.4؛ قبلاً انجام‌شده‌ها هم شمرده می‌شد) و لینک به همان فهرست فیلترشده ?>
    <a class="card stat" href="<?= e(url('/tasks?scope=mine&status=open&prio=high')) ?>" title="نمایش تسک‌های باز (در جریان) با اولویت زیاد"><div class="ico danger"><i class="fa-solid fa-fire"></i></div><div><div class="num"><?= e(fa_num($d['prio_open']['high'])) ?></div><div class="lbl">اولویت زیاد (باز)</div></div></a>
</div>

<div class="grid grid-wide mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> روند ۷ روز اخیر</h3></div><div class="chart" id="ch-trend"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bullseye"></i> نرخ تکمیل</h3></div>
        <div id="ch-ratio"></div>
        <?php if ($d['top_subject'] !== null): // فقط وقتی دست‌کم یک تسک لیبل دارد ?><div class="mt-2 small muted center">پرتکرارترین لیبل: <b class="text-green" data-css="color:var(--g-300)"><?= e($d['top_subject']) ?></b> · <?= e(fa_num($d['top_subject_count'])) ?> تسک</div><?php endif; ?></div>
</div>

<div class="grid grid-3 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> اولویت تسک‌های باز</h3></div><div class="chart" id="ch-prio"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-people-arrows"></i> گردش کار</h3></div>
        <div class="list">
            <?php // «باز از کل»؛ هر ردیف به فهرست فیلترشده‌ی خودش می‌رود ?>
            <a class="flex between dash-flow-row" href="<?= e(url('/tasks?scope=others&status=open')) ?>" title="تسک‌های باز واگذارشده به دیگران"><span class="muted">ساخته‌شده توسط من برای دیگران</span><b><?= e(fa_num($d['by_me_open'])) ?> باز <span class="faint xs">از <?= e(fa_num($d['by_me'])) ?></span></b></a>
            <a class="flex between dash-flow-row" href="<?= e(url('/tasks?scope=mine&status=open')) ?>" title="وظایف باز من"><span class="muted">واگذارشده به من از دیگران</span><b><?= e(fa_num($d['to_me_open'])) ?> باز <span class="faint xs">از <?= e(fa_num($d['to_me'])) ?></span></b></a>
            <a class="flex between dash-flow-row" href="<?= e(url('/texts')) ?>"><span class="muted">متن‌های آماده</span><b><?= e(fa_num($d['texts'])) ?></b></a>
        </div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-stopwatch"></i> میانگین زمان انجام</h3></div>
        <div class="list">
            <div class="flex between"><span class="muted"><i class="fa-solid fa-list-check"></i> تسک</span><b><?= e($time($d['task_time'])) ?></b></div>
            <div class="flex between"><span class="muted"><i class="fa-solid fa-phone-volume"></i> تماس</span><b><?= e($time($d['call_time'])) ?></b></div>
        </div></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-phone-volume"></i> درخواست‌های تماس</h3><a class="btn btn-sm" href="<?= e(url('/calls')) ?>">مشاهده</a></div>
        <div class="grid grid-3"><div class="center"><div class="num bold" data-css="font-size:1.4rem"><?= e(fa_num($d['calls']['total'])) ?></div><div class="xs muted">کل</div></div>
            <div class="center"><div class="num bold" data-css="font-size:1.4rem;color:var(--warn)"><?= e(fa_num($d['calls']['new'])) ?></div><div class="xs muted">جدید</div></div>
            <div class="center"><div class="num bold" data-css="font-size:1.4rem;color:var(--g-400)"><?= e(fa_num($d['calls']['done'])) ?></div><div class="xs muted">انجام‌شده</div></div></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-message"></i> متن‌های آماده</h3><a class="btn btn-sm" href="<?= e(url('/texts')) ?>">مشاهده</a></div>
        <div class="center"><div class="num bold" data-css="font-size:1.4rem"><?= e(fa_num($d['texts'])) ?></div><div class="xs muted">متن ذخیره‌شده</div></div></div>
</div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => null, 'context' => 'dashboard']); ?>
