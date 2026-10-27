<?php
/** @var array $d */
$s = $d['stats'];
$this->boot['d'] = ['stats' => $s, 'trend' => $d['trend'], 'calls' => $d['calls']];
$this->scripts[] = 'dashboard';
$time = static fn(array $t) => !empty($t['c']) ? duration_fa((float) $t['a']) : '—';
?>
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
        <div class="mt-2 small muted center">پرتکرارترین موضوع: <b class="text-green" style="color:var(--g-300)"><?= e($d['top_subject']) ?></b></div></div>
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
