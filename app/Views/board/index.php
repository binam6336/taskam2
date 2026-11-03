<?php
/** @var array $order ترتیب ستون‌های کاربر @var array $columns @var array $statuses @var array $projects @var array $filters @var array $archive @var int $archivable @var array $over ستون‌های بیشتر از سقف کاربر */
$this->scripts[] = 'board';
$total = array_sum(array_map('count', $columns));
?>
<div class="page-head"><h2><i class="fa-solid fa-table-columns" data-css="color:var(--g-400)"></i> بورد پروژه</h2>
    <button type="button" class="btn btn-primary" id="btn-new-task"><i class="fa-solid fa-plus"></i> تسک جدید</button></div>

<button type="button" class="btn kb-filter-toggle" id="kb-filter-toggle" aria-expanded="false" aria-controls="kb-filters"><i class="fa-solid fa-filter"></i> فیلترها<?php $fc = ($filters['project'] ? 1 : 0) + ($filters['q'] !== '' ? 1 : 0) + ($filters['mine'] ? 1 : 0); if ($fc): ?> <span class="badge-count"><?= e(fa_num($fc)) ?></span><?php endif; ?></button>
<form class="kb-filters card" id="kb-filters" method="get" action="<?= e(url('/board')) ?>">
    <select class="input" name="project" data-autosubmit data-logo-select>
        <option value="0">همه‌ی پروژه‌ها</option>
        <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" data-img="<?= e((string) project_image_url($p['profile_image'] ?? null)) ?>" <?= (int) $p['id'] === $filters['project'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?>
    </select>
    <div class="search kb-q"><i class="fa-solid fa-magnifying-glass"></i><input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(ph('board_search')) ?>"></div>
    <button type="button" class="btn btn-icon kb-arrange-btn" id="kb-arrange" aria-pressed="false" title="چیدمان ستون‌ها (جابه‌جایی ستون‌ها با کشیدن)" aria-label="چیدمان ستون‌ها"><i class="fa-solid fa-table-columns"></i><i class="fa-solid fa-arrows-left-right kb-arrange-sub"></i></button>
    <label class="check kb-mine"><input type="checkbox" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?> data-autosubmit><div><b>فقط مسئولیت من</b></div></label>
</form>

<div class="kb-arrange-bar card" id="kb-arrange-bar" hidden>
    <i class="fa-solid fa-arrows-left-right"></i>
    <div class="grow"><b>چیدمان ستون‌ها</b><span class="small muted"> — ستون‌ها را از سرشان بکشید و جابه‌جا کنید (یا با فلش‌ها). ترتیب برای شما ذخیره می‌شود.</span></div>
    <button type="button" class="btn btn-sm" id="kb-order-reset" <?= $order === \App\Services\BoardService::DEFAULT_ORDER ? 'disabled' : '' ?>><i class="fa-solid fa-rotate-left"></i> پیش‌فرض</button>
    <button type="button" class="btn btn-sm btn-primary" id="kb-arrange-done"><i class="fa-solid fa-check"></i> تمام</button>
</div>

<div data-live="board-body">
<?php if ($total === 0): ?>
<div class="card empty"><i class="fa-solid fa-table-columns"></i><p>تسکی برای نمایش در برد نیست. این‌جا تسک‌های خودتان و تسک‌های اعضایی که مدیر پروژه در «مشاهده‌ی تسک‌های» برایتان انتخاب کرده نمایش داده می‌شوند.</p></div>
<?php endif; ?>
<?php // نوار ستون‌ها با تعداد کارت: پرش به هر ستون (در موبایل و وقتی ستون‌ها از عرض صفحه بیرون می‌زنند) ?>
<nav class="kb-tabs" id="kb-tabs" aria-label="ستون‌های بورد">
<?php foreach ($order as $key): $n = $key === 'archived' ? (int) $archive['total'] : count($columns[$key]); $lbl = $key === 'archived' ? 'آرشیو' : $statuses[$key]['label']; ?>
    <button type="button" class="kb-tab<?= $n ? '' : ' empty' ?>" data-kb-goto="<?= e($key) ?>"><?= e($lbl) ?> <span><?= e(fa_num($n)) ?></span></button>
<?php endforeach; ?>
</nav>
<div class="kb-wrap" id="kb-wrap">
<button type="button" class="kb-nav kb-nav-start" data-kb-scroll="-1" aria-label="ستون‌های قبلی" hidden><i class="fa-solid fa-chevron-right"></i></button>
<button type="button" class="kb-nav kb-nav-end" data-kb-scroll="1" aria-label="ستون‌های بعدی" hidden><i class="fa-solid fa-chevron-left"></i></button>
<?php // ستون‌های خالی جمع‌شده (باریک با شمارنده؛ کلیک = باز کردن)؛ ستون شروع برای سرپرست «بررسی سرپرست»، برای بقیه «در حال انجام» ?>
<div class="kb-board" id="kb-board" data-kb-start="<?= e($startCol ?? 'in_progress') ?>">
<?php foreach ($order as $key): if ($key === 'archived'): ?>
    <section class="kb-col tone-archive<?= $archive['total'] ? '' : ' kb-collapsed' ?>" data-col="archived" aria-label="آرشیو">
        <header class="kb-col-head" title="تسک‌های انجام‌شده‌ای که بایگانی شده‌اند">
            <i class="fa-solid fa-box-archive"></i>
            <div class="grow"><b>آرشیو</b><span>Archived</span></div>
            <span class="kb-count"><?= e(fa_num($archive['total'])) ?></span>
        </header>
        <div class="kb-list" data-drop="archived">
        <?php foreach ($archive['cards'] as $t) { $this->partial('kb_card', ['t' => $t, 'key' => 'archived', 'filters' => $filters]); } ?>
        <?php if (!$archive['total']): ?><div class="kb-empty xs faint">آرشیو خالی است.</div><?php endif; ?>
        </div>
        <?php if ($archive['total'] > 0): ?>
        <footer class="kb-col-foot"><button type="button" class="btn btn-sm kb-more" data-kb-archive-more><i class="fa-solid fa-angles-down"></i> مشاهده بیشتر <span class="faint">(<?= e(fa_num($archive['total'])) ?>)</span></button></footer>
        <?php endif; ?>
    </section>
<?php continue; endif; $s = $statuses[$key]; $cards = $columns[$key]; $ov = $over[$key] ?? []; ?>
    <section class="kb-col tone-<?= e($s['tone']) ?><?= $ov ? ' kb-limit-over' : '' ?><?= $cards ? '' : ' kb-collapsed' ?>" data-col="<?= e($key) ?>" aria-label="<?= e($s['label']) ?>">
        <header class="kb-col-head" title="<?= e($s['desc']) ?>">
            <i class="fa-solid <?= e($s['icon']) ?>"></i>
            <div class="grow"><b><?= e($s['label']) ?></b><span><?= e($s['en']) ?></span></div>
            <?php if ($ov): ?>
            <button type="button" class="kb-limit-btn" data-kb-limit aria-expanded="false" aria-label="بیشتر از سقف تعداد تسک"><i class="fa-solid fa-exclamation"></i></button>
            <div class="kb-limit-pop" role="tooltip" hidden>
                <b><i class="fa-solid fa-triangle-exclamation"></i> تسک‌هایت از سقف تعیین‌شده برای این وضعیت بیشتر است.</b>
                <ul><?php foreach ($ov as $o): ?><li><?= count($over[$key]) > 1 || !$filters['project'] ? e($o['project']) . ': ' : '' ?><?= e(fa_num($o['count'])) ?> تسک، سقف <?= e(fa_num($o['limit'])) ?></li><?php endforeach; ?></ul>
                <p>تا وقتی تعدادشان کمتر از سقف نشود، نمی‌توانی تسک دیگری به «<?= e($s['label']) ?>» منتقل کنی.</p>
            </div>
            <?php endif; ?>
            <?php if ($key === 'done'): ?><button type="button" class="btn btn-sm kb-archive-all" data-kb-archive-all="<?= (int) $archivable ?>" <?= $archivable ? '' : 'disabled' ?> title="<?= $archivable ? 'انتقال همه‌ی تسک‌های انجام‌شده به آرشیو (فقط در برد؛ روی خود تسک‌ها اثری ندارد)' : 'تسک انجام‌شده‌ای برای آرشیو نیست' ?>" aria-label="آرشیو انجام‌شده‌ها"><i class="fa-solid fa-box-archive"></i> آرشیو</button><?php endif; ?>
            <span class="kb-count"><?= e(fa_num(count($cards))) ?></span>
        </header>
        <div class="kb-list" data-drop="<?= e($key) ?>">
        <?php foreach ($cards as $t) { $this->partial('kb_card', ['t' => $t, 'key' => $key, 'filters' => $filters]); } ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
</div>
</div>

<script type="application/json" id="kb-data"><?= json_html(['statuses' => array_map(static fn($s) => ['label' => $s['label'], 'icon' => $s['icon']], $statuses) + ['archived' => ['label' => 'آرشیو', 'icon' => 'fa-box-archive']]]) ?></script>

<!-- علت برگشت به بازبینی مسئول -->
<div class="modal" id="m-kb-rework"><div class="modal-box sm"><form id="f-kb-rework">
    <div class="modal-head"><h3><i class="fa-solid fa-rotate-left" data-css="color:#fb923c"></i> برگشت به بازبینی مسئول</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div class="field"><label>چه ایرادی دیدید که باید اصلاح شود؟ (اختیاری؛ به‌صورت یادداشت روی تسک ثبت و برای مسئول فرستاده می‌شود)</label>
        <textarea class="input" name="reason" rows="3" maxlength="255" placeholder="<?= e(ph('board_rework')) ?>"></textarea></div></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">برگرداندن برای اصلاح</button></div>
</form></div></div>

<!-- همه‌ی آرشیو -->
<div class="modal" id="m-kb-archive"><div class="modal-box lg">
    <div class="modal-head"><h3><i class="fa-solid fa-box-archive"></i> آرشیو تسک‌ها <span class="badge" id="kb-arch-count"></span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="search mb-1"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="kb-arch-q" placeholder="<?= e(ph('board_archive')) ?>" autocomplete="off"></div>
        <div id="kb-arch-list" class="list kb-arch-list"></div>
    </div>
</div></div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $taskProjects, 'assignees' => $assignees, 'shared' => null, 'context' => 'board']); ?>
