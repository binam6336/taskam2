<?php
/** @var array $project @var array $a @var array $k @var array $people @var array $speed */
use App\Services\TaskAnalytics;

$t = $a['totals'];
$sum = $k['summary'];
$this->boot['analytics'] = [
    'id' => (int) $project['id'], 'trend' => $a['trend'], 'totals' => $t, 'subjects' => $k['subjects'],
    'people' => $people, 'speed' => $speed, 'ranges' => array_map(static fn($r) => $r[0], TaskAnalytics::RANGES),
];
$this->scripts[] = 'analytics';
$pc = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
$toneBar = ['green' => '', 'blue' => 'blue', 'yellow' => 'yellow', 'red' => 'red', '' => ''];
?>
<div class="page-head"><div><a class="small muted" href="<?= e(url('/projects')) ?>"><i class="fa-solid fa-arrow-right"></i> پروژه‌ها</a><h2 class="mt-1">تحلیل «<?= e($project['title']) ?>»</h2></div></div>
<div class="grid grid-4 mb-2">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-list-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['total'])) ?></div><div class="lbl">کل تسک‌ها</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['done'])) ?></div><div class="lbl">انجام‌شده</div></div></div>
    <div class="card stat"><div class="ico warn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="num"><?= e(fa_num((int) $t['open'])) ?></div><div class="lbl">باز</div></div></div>
    <div class="card stat"><div class="ico danger"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="num"><?= e(fa_num((int) $t['overdue'])) ?></div><div class="lbl">عقب‌افتاده</div></div></div>
</div>

<div class="card mb-2 an-summary">
    <div class="card-head"><h3><i class="fa-solid fa-clipboard-check"></i> خلاصه‌ی تحلیل پروژه</h3>
        <?php if ($sum['rating']['score'] !== null): ?><span class="badge <?= e($sum['rating']['tone']) ?>">عملکرد کلی: <?= e($sum['rating']['label']) ?> (<?= e(fa_num($sum['rating']['score'])) ?> از ۱۰۰)</span><?php endif; ?></div>
    <p class="an-summary-text"><?= e($sum['text']) ?></p>
    <div class="an-split" role="img" aria-label="سهم وظایف">
        <div class="an-split-row"><span>انجام‌شده</span><div class="progress"><div style="width:<?= (int) $sum['done_pct'] ?>%"></div></div><b><?= e(fa_num($sum['done_pct'])) ?>٪</b></div>
        <div class="an-split-row"><span>با تأخیر</span><div class="progress yellow"><div style="width:<?= (int) $sum['delayed_pct'] ?>%"></div></div><b><?= e(fa_num($sum['delayed_pct'])) ?>٪</b></div>
        <div class="an-split-row"><span>انجام‌نشده</span><div class="progress red"><div style="width:<?= (int) $sum['open_pct'] ?>%"></div></div><b><?= e(fa_num($sum['open_pct'])) ?>٪</b></div>
    </div>
    <p class="hint">«با تأخیر» یعنی تسکی که دیرتر از سررسیدش انجام شده یا سررسیدش گذشته و هنوز باز است. ارزیابی عملکرد بر اساس استاندارد KPI سرعت (پایین صفحه) است.</p>
</div>

<div class="grid mb-2 an-row">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> ۱۴ روز اخیر</h3><span class="hint" style="margin:0">برای دیدن عدد، موس را روی نمودار ببرید</span></div><div class="chart" id="ch-trend"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> توزیع اولویت</h3></div><div id="ch-prio"></div>
        <p class="small muted center mt-2">میانگین زمان انجام: <b><?= e(duration_fa((float) $t['avg_hours'])) ?></b></p></div>
</div>

<div class="grid mb-2 an-row">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-gauge-high"></i> مقایسه‌ی سرعت انجام وظایف</h3></div>
        <div class="an-controls">
            <div class="an-people" id="sp-people">
                <?php foreach ($people as $i => $u): $on = in_array($u['id'], array_column($speed['series'], 'id'), true); ?>
                    <label class="an-chip"><input type="checkbox" value="<?= (int) $u['id'] ?>" <?= $on ? 'checked' : '' ?>><span><?= e($u['name']) ?></span></label>
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
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-tags"></i> توزیع موضوعات</h3></div>
        <?php if (!$k['subjects']): ?><p class="muted center">تسکی ثبت نشده است.</p><?php else: ?><div id="ch-subjects"></div><?php endif; ?></div>
</div>

<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-users"></i> عملکرد اعضا</h3></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>کل</th><th>انجام‌شده</th><th>باز</th><th>عقب‌افتاده</th><th style="width:32%">پیشرفت سرعت</th></tr></thead><tbody>
    <?php foreach ($k['members'] as $m): $r = $m['rating']; ?>
        <tr><td><div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?><?= e(full_name($m)) ?></div></td>
            <td><?= e(fa_num($m['total'])) ?></td><td><?= e(fa_num($m['done'])) ?></td><td><?= e(fa_num($m['open'])) ?></td>
            <td><?= (int) $m['overdue'] > 0 ? '<span class="badge red">' . e(fa_num($m['overdue'])) . '</span>' : '—' ?></td>
            <td><?php if ($r['score'] === null): ?><span class="faint small">هنوز تسکی انجام نداده</span><?php else: ?>
                <div class="an-speed"><div class="progress <?= e($toneBar[$r['tone']]) ?>" title="امتیاز سرعت <?= e(fa_num($r['score'])) ?> از ۱۰۰"><div style="width:<?= (int) $r['score'] ?>%"></div></div><b><?= e(fa_num($r['score'])) ?></b><span class="badge <?= e($r['tone']) ?>"><?= e($r['label']) ?></span></div><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="hint">«پیشرفت سرعت» امتیاز ۰ تا ۱۰۰ سرعت انجام وظایف هر عضو است: ۶۰٪ آن از زمان انجام (۲۴ ساعت یا کمتر = کامل، ۱۴ روز یا بیشتر = صفر) و ۴۰٪ از انجام به‌موقع (تا پایان سررسید) به دست می‌آید.</p>
</div>

<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-stopwatch"></i> KPI میانگین زمان انجام</h3></div>
    <div class="an-kpis">
    <?php foreach ($k['members'] as $m): $r = $m['rating']; ?>
        <div class="an-kpi">
            <div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?><b class="truncate"><?= e(full_name($m)) ?></b></div>
            <div class="an-kpi-v"><?= e($m['avg_hours'] === null ? '—' : duration_fa((float) $m['avg_hours'])) ?></div>
            <div class="xs muted">میانگین زمان انجام · <?= e(fa_num($m['done'])) ?> تسک</div>
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

<div class="card"><div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> آخرین تسک‌ها</h3></div>
    <?php if (!$a['recent']): ?><p class="muted">تسکی در این پروژه ثبت نشده است.</p><?php else: ?><div class="list"><?php foreach ($a['recent'] as $r): ?>
        <div class="flex between items-center gap-2"><span class="<?= $r['is_completed'] ? 'faint' : '' ?>" style="<?= $r['is_completed'] ? 'text-decoration:line-through' : '' ?>"><?= e($r['title']) ?></span>
            <span class="flex gap-1"><span class="badge <?= e($pc[$r['priority']][1]) ?>"><?= e($pc[$r['priority']][0]) ?></span><span class="xs muted"><?= e($r['first_name'] ? full_name($r) : '—') ?></span></span></div><?php endforeach; ?></div><?php endif; ?></div>
