<?php
/** @var array $d @var array $me @var array $focus @var array $waiting @var array $subjects @var array $projects @var array $assignees */
$s = $d['stats'];
$this->boot['d'] = ['stats' => $s, 'trend' => $d['trend'], 'calls' => $d['calls']];
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
    return '<a class="dash-task p-' . e($t['priority']) . '" href="' . e(url('/tasks?task=' . $t['share_token'])) . '" data-open-task="' . (int) $t['id'] . '">'
        . '<span class="dash-task-dot"></span><span class="grow truncate">' . e($t['title']) . '</span>'
        . ($t['project_title'] ? '<span class="dash-task-proj truncate"><i class="fa-solid fa-diagram-project"></i> ' . e($t['project_title']) . '</span>' : '')
        . '<span class="dash-task-due' . ($late ? ' late' : '') . '">' . e(trim($due . ' ' . $time)) . '</span></a>';
};
$w = $waiting;
$wCount = count($w['mentions']) + count($w['chats']) + count($w['colleagues']) + count($w['invites']);
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

        <?php if ($w['mentions']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-at"></i> منشن‌ها</b></div>
            <div class="list"><?php foreach ($w['mentions'] as $m): $an = full_name($m); ?>
                <a class="item dash-wait-item" href="<?= e(url('/tasks?task=' . ($m['share_token'] ?: ''))) ?>" data-open-task="<?= (int) $m['task_id'] ?>">
                    <?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e($an) ?></b> شما را در «<?= e($m['title']) ?>» منشن کرد</div><div class="xs faint"><?= e(time_ago($m['created_at'])) ?></div></div></a>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['chats']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-regular fa-comments"></i> پیام‌های خوانده‌نشده</b></div>
            <div class="list"><?php foreach ($w['chats'] as $c): $cn = full_name($c); ?>
                <a class="item dash-wait-item" href="<?= e(url('/chat?user=' . (int) $c['sender_id'])) ?>">
                    <?php $this->partial('avatar', ['name' => full_name($c), 'src' => avatar_url($c['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e($cn) ?></b></div><div class="xs faint"><?= e(time_ago($c['last_at'])) ?></div></div>
                    <span class="badge-count"><?= e(fa_num($c['cnt'])) ?></span></a>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['colleagues']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-user-plus"></i> درخواست همکاری</b></div>
            <div class="list"><?php foreach ($w['colleagues'] as $r): ?>
                <div class="item" id="dash-cr-<?= (int) $r['id'] ?>">
                    <?php $this->partial('avatar', ['name' => full_name($r), 'src' => avatar_url($r['avatar'] ?? null), 'size' => 'sm']); ?>
                    <div class="grow min0"><div class="small truncate"><b><?= e(full_name($r)) ?></b> می‌خواهد همکار شما شود</div><div class="xs faint"><?= e(time_ago($r['created_at'])) ?></div></div>
                    <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/colleagues/requests/' . $r['id'] . '/accept')) ?>" data-after="reload">پذیرفتن</button><button class="btn btn-sm" data-post="<?= e(url('/colleagues/requests/' . $r['id'] . '/reject')) ?>" data-after="reload">رد</button></div></div>
            <?php endforeach; ?></div></div>
        <?php endif; ?>

        <?php if ($w['invites']): ?>
        <div class="dash-sec"><div class="dash-sec-head"><b><i class="fa-solid fa-envelope-open-text"></i> دعوت به پروژه</b></div>
            <div class="list"><?php foreach ($w['invites'] as $r): ?>
                <div class="item">
                    <span class="avatar sm sq"><?php if ($r['profile_image']): ?><img src="<?= e(project_image_url($r['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial($r['title'])) ?><?php endif; ?></span>
                    <div class="grow min0"><div class="small truncate">عضویت در <b>«<?= e($r['title']) ?>»</b></div><div class="xs faint">دعوت از <?= e(full_name($r)) ?> • <?= e(time_ago($r['created_at'])) ?></div></div>
                    <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/projects/requests/' . $r['id'] . '/respond')) ?>" data-data='{"action":"accept"}' data-after="reload">پذیرفتن</button><button class="btn btn-sm" data-post="<?= e(url('/projects/requests/' . $r['id'] . '/respond')) ?>" data-data='{"action":"reject"}' data-after="reload">رد</button></div></div>
            <?php endforeach; ?></div></div>
        <?php endif; ?>
    </div>
</div>
</div>
<div class="grid grid-4 mb-2">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-list-check"></i></div><div><div class="num"><?= e(fa_num($s['total'])) ?></div><div class="lbl">کل تسک‌های من</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="num"><?= e(fa_num($s['completed'])) ?></div><div class="lbl">انجام‌شده</div></div></div>
    <div class="card stat"><div class="ico warn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="num"><?= e(fa_num($s['pending'])) ?></div><div class="lbl">در جریان</div></div></div>
    <div class="card stat"><div class="ico danger"><i class="fa-solid fa-fire"></i></div><div><div class="num"><?= e(fa_num($s['high'])) ?></div><div class="lbl">اولویت زیاد</div></div></div>
</div>

<div class="grid grid-wide mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> روند ۷ روز اخیر</h3></div><div class="chart" id="ch-trend"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bullseye"></i> نرخ تکمیل</h3></div>
        <div id="ch-ratio"></div>
        <div class="mt-2 small muted center">پرتکرارترین لیبل: <b class="text-green" style="color:var(--g-300)"><?= e($d['top_subject']) ?></b></div></div>
</div>

<div class="grid grid-3 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> اولویت‌ها</h3></div><div class="chart" id="ch-prio"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-people-arrows"></i> گردش کار</h3></div>
        <div class="list">
            <div class="flex between"><span class="muted">ساخته‌شده توسط من برای دیگران</span><b><?= e(fa_num($d['by_me'])) ?></b></div>
            <div class="flex between"><span class="muted">واگذارشده به من از دیگران</span><b><?= e(fa_num($d['to_me'])) ?></b></div>
            <div class="flex between"><span class="muted">متن‌های آماده</span><b><?= e(fa_num($d['texts'])) ?></b></div>
        </div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-stopwatch"></i> میانگین زمان انجام</h3></div>
        <div class="list">
            <div class="flex between"><span class="muted"><i class="fa-solid fa-list-check"></i> تسک</span><b><?= e($time($d['task_time'])) ?></b></div>
            <div class="flex between"><span class="muted"><i class="fa-solid fa-phone-volume"></i> تماس</span><b><?= e($time($d['call_time'])) ?></b></div>
        </div></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-phone-volume"></i> درخواست‌های تماس</h3><a class="btn btn-sm" href="<?= e(url('/calls')) ?>">مشاهده</a></div>
        <div class="grid grid-3"><div class="center"><div class="num bold" style="font-size:1.4rem"><?= e(fa_num($d['calls']['total'])) ?></div><div class="xs muted">کل</div></div>
            <div class="center"><div class="num bold" style="font-size:1.4rem;color:var(--warn)"><?= e(fa_num($d['calls']['new'])) ?></div><div class="xs muted">جدید</div></div>
            <div class="center"><div class="num bold" style="font-size:1.4rem;color:var(--g-400)"><?= e(fa_num($d['calls']['done'])) ?></div><div class="xs muted">انجام‌شده</div></div></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-message"></i> متن‌های آماده</h3><a class="btn btn-sm" href="<?= e(url('/texts')) ?>">مشاهده</a></div>
        <div class="center"><div class="num bold" style="font-size:1.4rem"><?= e(fa_num($d['texts'])) ?></div><div class="xs muted">متن ذخیره‌شده</div></div></div>
</div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => null, 'context' => 'dashboard']); ?>
