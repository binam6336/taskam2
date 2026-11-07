<?php
/** @var array $project @var array $a @var array $k @var array $people @var array $speed */
use App\Services\TaskAnalytics;

$t = $a['totals'];
$sum = $k['summary'];
$this->boot['analytics'] = [
    'id' => pid('projects', $project), 'trend' => $a['trend'], 'totals' => $t, 'subjects' => $k['subjects'], 'label_count' => $k['label_count'],
    'people' => array_map(static fn($u) => ['id' => pid('users', $u['id'])] + $u, $people), 'speed' => \App\Controllers\ProjectController::speedOut($speed), 'ranges' => array_map(static fn($r) => $r[0], TaskAnalytics::RANGES),
];
$this->scripts[] = 'analytics';
$pc = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
$toneBar = ['green' => '', 'blue' => 'blue', 'yellow' => 'yellow', 'red' => 'red', '' => ''];
// کارت‌ها و اعضا لینک به صفحه‌ی تسک‌ها با فیلتر همین پروژه
$tl = static fn(array $q) => url('/tasks') . '?' . http_build_query(['scope' => 'project', 'project' => pid('projects', $project)] + $q);
?>
<div class="page-head"><div><a class="small muted an-back" href="<?= e(url('/projects')) ?>"><i class="fa-solid fa-arrow-right"></i> پروژه‌ها</a><h2 class="mt-1">تحلیل «<?= e($project['title']) ?>»</h2></div></div>
<?php // کارت‌ها هم‌تعریف با «خلاصه‌ی تحلیل» (v5.4): «تأییدشده» (تکمیل‌شده با تأیید سرپرست) جدا از «منتظر تأیید» (تحویل‌شده، هنوز تأییدنشده)؛ تحویل‌شده = جمع این دو ?>
<div class="grid an-cards mb-2">
    <a class="card stat" href="<?= e($tl(['status' => 'all'])) ?>" title="نمایش همه‌ی تسک‌های پروژه"><div class="ico"><i class="fa-solid fa-list-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['total'])) ?></div><div class="lbl">کل تسک‌ها</div></div></a>
    <a class="card stat" href="<?= e($tl(['status' => 'done'])) ?>" title="تسک‌هایی که سرپرست تأیید و تکمیل کرده است"><div class="ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['done'])) ?></div><div class="lbl">تأییدشده</div></div></a>
    <a class="card stat" href="<?= e(url('/board?project=' . pid('projects', $project))) ?>" title="تحویل‌شده و منتظر تأیید سرپرست (ستون «بررسی سرپرست» بورد)"><div class="ico purple"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="num"><?= e(fa_num((int) $t['review'])) ?></div><div class="lbl">منتظر تأیید</div></div></a>
    <a class="card stat" href="<?= e($tl(['status' => 'open'])) ?>" title="تسک‌های باز پروژه (هنوز تحویل نشده)"><div class="ico warn"><i class="fa-solid fa-person-digging"></i></div><div><div class="num"><?= e(fa_num((int) $t['open'] - (int) $t['review'])) ?></div><div class="lbl">باز (تحویل‌نشده)</div></div></a>
    <a class="card stat" href="<?= e($tl(['status' => 'open', 'when' => 'overdue'])) ?>" title="نمایش تسک‌های عقب‌افتاده‌ی پروژه"><div class="ico danger"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="num"><?= e(fa_num((int) $t['overdue'])) ?></div><div class="lbl">عقب‌افتاده</div></div></a>
</div>

<div class="card mb-2 an-summary">
    <div class="card-head"><h3><i class="fa-solid fa-clipboard-check"></i> خلاصه‌ی تحلیل پروژه</h3>
        <?php if ($sum['rating']['score'] !== null): ?><span class="badge <?= e($sum['rating']['tone']) ?>">عملکرد کلی: <?= e($sum['rating']['label']) ?> (<?= e(fa_num($sum['rating']['score'])) ?> از ۱۰۰)</span><?php endif; ?></div>
    <p class="an-summary-text"><?= e($sum['text']) ?></p>
    <?php $sp = $sum['split']; $cn = $sum['counts']; $segs = [['done_ontime', 'به‌موقع تحویل‌شده', 'ok'], ['done_late', 'با تأخیر تحویل‌شده', 'warn'], ['open_ontime', 'باز (در مهلت)', 'info'], ['open_late', 'باز و عقب‌افتاده', 'danger']]; ?>
    <?php // یک نوار انباشته با جمع ۱۰۰٪ (بخش‌ها هم‌پوشانی ندارند) ?>
    <div class="an-stack" role="img" aria-label="<?= e(implode('، ', array_map(static fn($g) => $g[1] . ' ' . fa_num($sp[$g[0]]) . '٪', $segs))) ?>">
        <?php foreach ($segs as [$sk, $l, $tone]): if ($sp[$sk] > 0): ?><div class="an-stack-seg <?= $tone ?>" data-css="width:<?= (int) $sp[$sk] ?>%" title="<?= e($l) ?>: <?= e(fa_num($sp[$sk])) ?>٪ (<?= e(fa_num($cn[$sk])) ?> تسک)"></div><?php endif; endforeach; ?>
    </div>
    <div class="an-stack-legend">
        <?php foreach ($segs as [$sk, $l, $tone]): ?><span><i class="<?= $tone ?>"></i><?= e($l) ?> <b><?= e(fa_num($sp[$sk])) ?>٪</b> <span class="faint">(<?= e(fa_num($cn[$sk])) ?> تسک)</span></span><?php endforeach; ?>
    </div>
    <p class="hint">بخش‌ها روی هم ۱۰۰٪ همه‌ی وظایف‌اند. «تحویل‌شده» یعنی مسئول آن را تحویل داده (تأییدشده + منتظر تأیید سرپرست، همان دو کارت بالا)؛ «با تأخیر» یعنی روز تحویل بعد از روز سررسید بوده (ساعت سررسید حساب نمی‌شود)؛ «عقب‌افتاده» یعنی روز سررسیدش گذشته و هنوز تحویل نشده. ارزیابی عملکرد بر اساس استاندارد KPI سرعت (پایین صفحه) است.</p>
</div>

<div class="grid mb-2 an-row">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> ۱۴ روز اخیر</h3><span class="hint" data-css="margin:0">برای دیدن عدد، موس را روی نمودار ببرید</span></div><div class="chart" id="ch-trend"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> توزیع اولویت</h3></div><div id="ch-prio"></div>
        <p class="small muted center mt-2">میانگین زمان انجام: <b><?= e(duration_fa((float) $t['avg_hours'])) ?></b></p></div>
</div>

<div class="grid mb-2 an-row">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-gauge-high"></i> مقایسه‌ی سرعت انجام وظایف</h3></div>
        <div class="an-controls">
            <div class="an-people" id="sp-people">
                <?php foreach ($people as $i => $u): $on = in_array($u['id'], array_column($speed['series'], 'id'), true); ?>
                    <label class="an-chip"><input type="checkbox" value="<?= e(pid('users', $u['id'])) ?>" <?= $on ? 'checked' : '' ?>><span><?= e($u['name']) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-1 wrap items-center">
                <div class="seg-btns" id="sp-range"><?php foreach (TaskAnalytics::RANGES as $key => [$label]): ?><button type="button" data-v="<?= e($key) ?>" class="<?= $key === '1m' ? 'on' : '' ?>"><?= e($label) ?></button><?php endforeach; ?></div>
                <div class="seg-btns" id="sp-metric"><button type="button" data-v="score" class="on">امتیاز سرعت</button><button type="button" data-v="count">تعداد انجام‌شده</button></div>
            </div>
        </div>
        <div class="chart" id="ch-speed"></div>
        <p class="hint">حداکثر ۵ کاربر را برای مقایسه انتخاب کنید. «امتیاز سرعت» میانگین امتیاز تسک‌هایی است که در هر بازه انجام شده‌اند (۰ تا ۱۰۰)؛ نقطه‌ی خالی یعنی در آن بازه تسکی انجام نشده است.</p>
    </div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-tags"></i> توزیع لیبل‌ها</h3></div>
        <?php // «بدون لیبل» شمرده نمی‌شود؛ اگر هیچ تسکی لیبل ندارد، به‌جای نمودار راهنما ?>
        <?php if (!$k['subjects']): ?><div class="empty small"><i class="fa-solid fa-tags"></i><p>هیچ تسکی در این پروژه لیبل ندارد.</p><a class="btn btn-sm" href="<?= e(url('/labels')) ?>"><i class="fa-solid fa-plus"></i> افزودن لیبل</a></div><?php else: ?><div id="ch-subjects"></div><p class="hint center">سهم هر لیبل از تسک‌های لیبل‌دار (تسک‌های بدون لیبل شمرده نمی‌شوند).</p><?php endif; ?></div>
</div>

<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-users"></i> عملکرد اعضا</h3></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>کل</th><th>انجام‌شده</th><th title="تحویل شده و منتظر تأیید سرپرست">منتظر تأیید</th><th>باز</th><th>عقب‌افتاده</th><th data-css="width:32%">پیشرفت سرعت</th></tr></thead><tbody>
    <?php foreach ($k['members'] as $m): $r = $m['rating']; ?>
        <tr><td><?php if (!empty($m['is_group'])): // گروه (v5.10) ?><a class="flex items-center gap-1 an-member-link" href="<?= e($tl(['status' => 'all', 'group' => pid('project_groups', $m['id'])])) ?>" title="تسک‌های <?= e(full_name($m)) ?>"><span class="avatar sm sq an-grp-av" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span><?= e(full_name($m)) ?></a>
            <?php else: ?><a class="flex items-center gap-1 an-member-link" href="<?= e($tl(['status' => 'all', 'assignee' => pid('users', $m['id'])])) ?>" title="تسک‌های <?= e(full_name($m)) ?> در این پروژه"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?><?= e(full_name($m)) ?></a><?php endif; ?></td>
            <td><?= e(fa_num($m['total'])) ?></td><td><?= e(fa_num($m['done'])) ?></td><td><?= (int) $m['waiting'] > 0 ? '<span class="badge purple">' . e(fa_num($m['waiting'])) . '</span>' : '—' ?></td><td><?= e(fa_num($m['open'])) ?></td>
            <td><?= (int) $m['overdue'] > 0 ? '<span class="badge red">' . e(fa_num($m['overdue'])) . '</span>' : '—' ?></td>
            <td><?php if ($r['score'] === null): ?><span class="faint small">هنوز تسکی انجام نداده</span><?php else: ?>
                <div class="an-speed"><div class="progress <?= e($toneBar[$r['tone']]) ?>" title="امتیاز سرعت <?= e(fa_num($r['score'])) ?> از ۱۰۰"><div data-css="width:<?= (int) $r['score'] ?>%"></div></div><b><?= e(fa_num($r['score'])) ?></b><span class="badge <?= e($r['tone']) ?>"><?= e($r['label']) ?></span></div><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="hint">«پیشرفت سرعت» امتیاز ۰ تا ۱۰۰ سرعت انجام وظایف هر عضو است: ۶۰٪ آن از زمان انجام (۲۴ ساعت یا کمتر = کامل، ۱۴ روز یا بیشتر = صفر) و ۴۰٪ از تحویل به‌موقع (تا پایان روز سررسید) به دست می‌آید. زمان مسئول تا لحظه‌ی تحویل برای بررسی حساب می‌شود؛ زمان انتظار برای تأیید سرپرست به پای مسئول نوشته نمی‌شود.</p>
</div>

<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-stopwatch"></i> KPI میانگین زمان انجام</h3></div>
    <div class="an-kpis">
    <?php foreach ($k['members'] as $m): $r = $m['rating']; ?>
        <div class="an-kpi">
            <div class="flex items-center gap-1"><?php if (!empty($m['is_group'])): ?><span class="avatar sm sq an-grp-av" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span><?php else: $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); endif; ?><b class="truncate"><?= e(full_name($m)) ?></b></div>
            <div class="an-kpi-v"><?= e($m['avg_hours'] === null ? '—' : duration_fa((float) $m['avg_hours'])) ?></div>
            <div class="xs muted">میانگین زمان تا تحویل · <?= e(fa_num($m['done'] + $m['waiting'])) ?> تسک</div>
            <div class="flex between items-center mt-1"><span class="xs muted">به‌موقع: <?= $m['on_time'] === null ? '—' : e(fa_num($m['on_time'])) . '٪' ?></span><span class="badge <?= e($r['tone']) ?>"><?= e($r['label']) ?></span></div>
        </div>
    <?php endforeach; ?>
    </div>
    <details class="an-std mt-2"><summary><i class="fa-solid fa-circle-info"></i> استاندارد ارزیابی (KPI)</summary>
        <table class="table mt-1"><thead><tr><th>امتیاز سرعت</th><th>ارزیابی</th></tr></thead><tbody>
        <?php $prev = 100; foreach (TaskAnalytics::RATINGS as [$min, $label, $tone]): ?>
            <tr><td><?= e(fa_num($min)) ?> تا <?= e(fa_num($prev)) ?></td><td><span class="badge <?= e($tone) ?>"><?= e($label) ?></span></td></tr>
        <?php $prev = $min - 1; endforeach; ?></tbody></table></details>
</div>

<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-user-check"></i> KPI میانگین زمان تأیید</h3></div>
    <?php // سرپرست‌ها: فاصله‌ی تحویل مسئول تا تأیید در «بررسی سرپرست»، به نام تأییدکننده ?>
    <?php if (!$k['reviewers']): ?><p class="muted small">هنوز تسکی در «بررسی سرپرست» تأیید نشده است.</p><?php else: ?>
    <div class="an-kpis"><?php foreach ($k['reviewers'] as $rv): ?>
        <div class="an-kpi">
            <div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($rv), 'src' => avatar_url($rv['avatar']), 'size' => 'sm']); ?><b class="truncate"><?= e(full_name($rv)) ?></b></div>
            <div class="an-kpi-v"><?= e(duration_fa((float) $rv['avg_hours'])) ?></div>
            <div class="xs muted">میانگین زمان تأیید · <?= e(fa_num($rv['count'])) ?> تسک</div>
        </div><?php endforeach; ?></div><?php endif; ?>
    <p class="hint">از لحظه‌ی تحویل تسک برای بررسی تا تأیید و بستن آن.</p>
</div>

<div class="card"><div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> آخرین تسک‌ها</h3></div>
    <?php if (!$a['recent']): ?><p class="muted">تسکی در این پروژه ثبت نشده است.</p><?php else: ?><div class="list"><?php foreach ($a['recent'] as $r): ?>
        <div class="flex between items-center gap-2"><span class="<?= $r['is_completed'] ? 'faint' : '' ?>" data-css="<?= $r['is_completed'] ? 'text-decoration:line-through' : '' ?>"><?= e($r['title']) ?></span>
            <span class="flex gap-1"><span class="badge <?= e($pc[$r['priority']][1]) ?>"><?= e($pc[$r['priority']][0]) ?></span><span class="xs muted"><?= e($r['first_name'] ? full_name($r) : '—') ?></span></span></div><?php endforeach; ?></div><?php endif; ?></div>
