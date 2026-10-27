<?php
/** @var array $project @var array $a */
$t = $a['totals'];
$this->boot['analytics'] = ['trend' => $a['trend'], 'totals' => $t, 'members' => array_map(static fn($m) => ['name' => full_name($m), 'done' => (int) $m['done'], 'open' => (int) $m['open']], $a['members'])];
$this->scripts[] = 'analytics';
$pc = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
?>
<div class="page-head"><div><a class="small muted" href="<?= e(url('/projects')) ?>"><i class="fa-solid fa-arrow-right"></i> پروژه‌ها</a><h2 class="mt-1">تحلیل «<?= e($project['title']) ?>»</h2></div></div>
<div class="grid grid-4 mb-2">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-list-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['total'])) ?></div><div class="lbl">کل تسک‌ها</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="num"><?= e(fa_num((int) $t['done'])) ?></div><div class="lbl">انجام‌شده</div></div></div>
    <div class="card stat"><div class="ico warn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="num"><?= e(fa_num((int) $t['open'])) ?></div><div class="lbl">باز</div></div></div>
    <div class="card stat"><div class="ico danger"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="num"><?= e(fa_num((int) $t['overdue'])) ?></div><div class="lbl">عقب‌افتاده</div></div></div>
</div>
<div class="grid mb-2" style="grid-template-columns:2fr 1fr">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> ۱۴ روز اخیر</h3></div><div class="chart" id="ch-trend"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-flag"></i> توزیع اولویت</h3></div><div id="ch-prio"></div>
        <p class="small muted center mt-2">میانگین زمان انجام: <b><?= e(duration_fa((float) $t['avg_hours'])) ?></b></p></div>
</div>
<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-users"></i> عملکرد اعضا</h3></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>کل</th><th>انجام‌شده</th><th>باز</th><th>عقب‌افتاده</th><th style="width:30%">پیشرفت</th></tr></thead><tbody>
    <?php foreach ($a['members'] as $m): $pct = (int) $m['total'] > 0 ? (int) round($m['done'] / $m['total'] * 100) : 0; ?>
        <tr><td><div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?><?= e(full_name($m)) ?></div></td>
            <td><?= e(fa_num($m['total'])) ?></td><td><?= e(fa_num($m['done'])) ?></td><td><?= e(fa_num($m['open'])) ?></td>
            <td><?= (int) $m['overdue'] > 0 ? '<span class="badge red">' . e(fa_num($m['overdue'])) . '</span>' : '—' ?></td>
            <td><div class="progress"><div style="width:<?= $pct ?>%"></div></div></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> آخرین تسک‌ها</h3></div>
    <?php if (!$a['recent']): ?><p class="muted">تسکی در این پروژه ثبت نشده است.</p><?php else: ?><div class="list"><?php foreach ($a['recent'] as $r): ?>
        <div class="flex between items-center gap-2"><span class="<?= $r['is_completed'] ? 'faint' : '' ?>" style="<?= $r['is_completed'] ? 'text-decoration:line-through' : '' ?>"><?= e($r['title']) ?></span>
            <span class="flex gap-1"><span class="badge <?= e($pc[$r['priority']][1]) ?>"><?= e($pc[$r['priority']][0]) ?></span><span class="xs muted"><?= e($r['first_name'] ? full_name($r) : '—') ?></span></span></div><?php endforeach; ?></div><?php endif; ?></div>
