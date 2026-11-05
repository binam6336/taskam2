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
/** یک ردیف تسک در باکس امروز/عقب‌افتاده (کلیک = جزئیات تسک در همین صفحه) */
$taskRow = static function (array $t, bool $late): string {
    $due = $late ? jdate($t['due_date'] . ' 00:00:00', false) : '';
    $time = $t['due_time'] ? fa_num(substr((string) $t['due_time'], 0, 5)) : '';
    // امروز با ساعت گذشته: در «امروز» می‌ماند (عقب‌افتاده فقط روزهای قبل) و برچسب قرمز «ساعت گذشته» می‌گیرد
    $hourPast = !$late && $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time();
    return '<a class="dash-task p-' . e($t['priority']) . '" href="' . e(url('/tasks?task=' . $t['share_token'])) . '" data-open-task="' . e(pid('tasks', $t)) . '">'
        . '<span class="dash-task-dot"></span><span class="grow truncate">' . e($t['title']) . '</span>'
        . ($t['project_title'] ? '<span class="dash-task-proj truncate"><i class="fa-solid fa-diagram-project"></i> ' . e($t['project_title']) . '</span>' : '')
        . '<span class="dash-task-due' . ($late || $hourPast ? ' late' : '') . '">' . e(trim($due . ' ' . $time)) . ($hourPast ? ' <span class="hour-past">ساعت گذشته</span>' : '') . '</span></a>';
};
$w = $waiting;
$wCount = count($w['mentions']) + count($w['chats']) + count($w['colleagues']) + count($w['invites']) + count($w['review']) + count($w['rework']);
?>
<div data-live="dash-top">
<div class="dash-hello mb-2">
    <h2>سلام <?= e(($me['first_name'] ?? '') !== '' ? $me['first_name'] : full_name($me)) ?> عزیز <span aria-hidden="true">👋</span></h2>
    <p><?= e($summary) ?></p>
</div>

<div class="grid grid-2 mb-2 dash-main">
    <div class="card dash-focus">
        <div class="card-head"><h3><i class="fa-solid fa-bolt"></i> کارهای مهم</h3></div>
        <div class="dash-sec">
            <div class="dash-sec-head"><b><i class="fa-regular fa-calendar-check"></i> تسک‌های امروز <span class="badge"><?= e(fa_num($nToday)) ?></span></b>
                <?php if ($nToday > count($focus['today'])): ?><a class="xs" href="<?= e(url('/tasks?when=today')) ?>">همه</a><?php endif; ?></div>
            <?php if ($focus['today']): ?><div class="dash-tasks"><?php foreach ($focus['today'] as $t) { echo $taskRow($t, false); } ?></div>
            <?php else: ?><div class="faint small dash-none">برای امروز تسکی ندارید.</div><?php endif; ?>
        </div>
        <div class="dash-sec">
            <div class="dash-sec-head"><b class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> تسک‌های عقب‌افتاده <span class="badge red"><?= e(fa_num($nLate)) ?></span></b>
                <?php if ($nLate > count($focus['overdue'])): ?><a class="xs" href="<?= e(url('/tasks?when=overdue')) ?>">همه</a><?php endif; ?></div>
            <?php if ($focus['overdue']): ?><div class="dash-tasks"><?php foreach ($focus['overdue'] as $t) { echo $taskRow($t, true); } ?></div>
            <?php else: ?><div class="faint small dash-none">تسک عقب‌افتاده‌ای ندارید. 👌</div><?php endif; ?>
        </div>
    </div>

    <div class="card dash-wait">
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
