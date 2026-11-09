<?php
/**
 * بورد یک پروژه (v6.1). @var array $order @var array $columns @var array $statuses ستون‌های همین پروژه @var array $projects @var array|null $project
 * @var array $filters @var array $archive @var int $archivable @var array $over @var bool $canManage @var bool $customBoard
 */
$this->scripts[] = 'board';
$total = array_sum(array_map('count', $columns));
$ppid = pid('projects', (int) $filters['project']);
?>
<div class="kb-top">
    <div class="kb-proj-switch">
        <span class="avatar sq"><?php if (!empty($project['profile_image'])): ?><img loading="lazy" decoding="async" src="<?= e((string) project_image_url($project['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial((string) ($project['title'] ?? '؟'))) ?><?php endif; ?></span>
        <label class="sr-only" for="kb-project">پروژه</label>
        <select class="input kb-project-sel" id="kb-project" data-kb-project aria-label="انتخاب پروژه">
            <?php foreach ($projects as $p): ?><option value="<?= e(pid('projects', $p)) ?>" <?= (int) $p['id'] === (int) $filters['project'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <form class="kb-filters" id="kb-filters" method="get" action="<?= e(url('/board')) ?>">
        <input type="hidden" name="project" value="<?= e($ppid) ?>">
        <div class="search kb-q"><i class="fa-solid fa-magnifying-glass"></i><input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(ph('board_search')) ?>" aria-label="جستجو در همه‌ی تسک‌های پروژه"></div>
        <label class="check kb-mine"><input type="checkbox" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?> data-autosubmit><div><b>فقط مسئولیت من</b></div></label>
    </form>
    <div class="kb-top-acts">
        <?php if ($canManage): ?><button type="button" class="btn btn-icon" id="kb-settings" data-project="<?= e($ppid) ?>" title="تنظیمات بورد" aria-label="تنظیمات بورد"><i class="fa-solid fa-gear"></i></button><?php endif; ?>
        <button type="button" class="btn btn-primary" id="btn-new-task"><i class="fa-solid fa-plus"></i> <span>تسک جدید</span></button>
    </div>
</div>
<?php if ($filters['q'] !== ''): ?><p class="kb-search-note small muted"><i class="fa-solid fa-circle-info"></i> جستجو در همه‌ی تسک‌های این پروژه (هر مسئول و سازنده‌ای؛ جز تسک‌های محرمانه‌ای که به شما مربوط نیست).</p><?php endif; ?>

<div data-live="board-body">
<?php if ($total === 0 && !$archive['total']): ?>
<div class="card empty kb-empty-board"><i class="fa-solid fa-table-columns"></i><p><?= $filters['q'] !== '' ? 'تسکی با این جستجو در این پروژه پیدا نشد.' : 'هنوز تسکی در بورد این پروژه نیست.' ?></p></div>
<?php endif; ?>
<?php // نوار ستون‌ها با تعداد کارت: پرش به هر ستون (موبایل و وقتی ستون‌ها بیرون از دید هستند) ?>
<nav class="kb-tabs" id="kb-tabs" aria-label="ستون‌های بورد">
<?php foreach ($order as $key): $n = $key === 'archived' ? (int) $archive['total'] : count($columns[$key] ?? []); $lbl = $key === 'archived' ? 'آرشیو' : $statuses[$key]['label']; ?>
    <button type="button" class="kb-tab<?= $n ? '' : ' empty' ?>" data-kb-goto="<?= e($key) ?>"><?= e($lbl) ?> <span><?= e(fa_num($n)) ?></span></button>
<?php endforeach; ?>
</nav>
<div class="kb-wrap" id="kb-wrap">
<button type="button" class="kb-nav kb-nav-start" data-kb-scroll="-1" aria-label="ستون‌های قبلی" hidden><i class="fa-solid fa-chevron-right"></i></button>
<button type="button" class="kb-nav kb-nav-end" data-kb-scroll="1" aria-label="ستون‌های بعدی" hidden><i class="fa-solid fa-chevron-left"></i></button>
<div class="kb-board" id="kb-board" data-kb-start="<?= e($startCol) ?>" data-project="<?= e($ppid) ?>">
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
<?php continue; endif; $s = $statuses[$key]; $cards = $columns[$key] ?? []; $ov = $over[$key] ?? []; ?>
    <section class="kb-col tone-<?= e($s['tone']) ?><?= $ov ? ' kb-limit-over' : '' ?><?= $cards ? '' : ' kb-collapsed' ?>" data-col="<?= e($key) ?>" aria-label="<?= e($s['label']) ?>">
        <header class="kb-col-head" title="<?= e($s['label'] . ($s['desc'] !== '' ? ' — ' . $s['desc'] : '')) ?>">
            <i class="fa-solid <?= e($s['icon']) ?>"></i>
            <div class="grow min0"><b class="truncate"><?= e($s['label']) ?></b></div>
            <?php if ($ov): ?>
            <button type="button" class="kb-limit-btn" data-kb-limit aria-expanded="false" aria-label="بیشتر از سقف تعداد تسک"><i class="fa-solid fa-exclamation"></i></button>
            <div class="kb-limit-pop" role="tooltip" hidden>
                <b><i class="fa-solid fa-triangle-exclamation"></i> تسک‌هایت از سقف تعیین‌شده برای این وضعیت بیشتر است.</b>
                <ul><?php foreach ($ov as $o): ?><li><?= e(fa_num($o['count'])) ?> تسک، سقف <?= e(fa_num($o['limit'])) ?></li><?php endforeach; ?></ul>
                <p>تا وقتی تعدادشان کمتر از سقف نشود، نمی‌توانی تسک دیگری به «<?= e($s['label']) ?>» منتقل کنی.</p>
            </div>
            <?php endif; ?>
            <?php if ($key === 'done'): ?><button type="button" class="btn btn-sm kb-archive-all" data-kb-archive-all="<?= (int) $archivable ?>" <?= $archivable ? '' : 'disabled' ?> title="<?= $archivable ? 'انتقال همه‌ی تسک‌های انجام‌شده به آرشیو (فقط در برد؛ روی خود تسک‌ها اثری ندارد)' : 'تسک انجام‌شده‌ای برای آرشیو نیست' ?>" aria-label="آرشیو انجام‌شده‌ها"><i class="fa-solid fa-box-archive"></i></button><?php endif; ?>
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

<script type="application/json" id="kb-data"><?= json_html(['project' => $ppid, 'statuses' => array_map(static fn($s) => ['label' => $s['label'], 'icon' => $s['icon']], $statuses) + ['archived' => ['label' => 'آرشیو', 'icon' => 'fa-box-archive']]]) ?></script>

<!-- علت برگشت به بازبینی مسئول -->
<div class="modal" id="m-kb-rework"><div class="modal-box sm"><form id="f-kb-rework">
    <div class="modal-head"><h3><i class="dc-483f44 fa-solid fa-rotate-left"></i> برگشت به «<?= e($statuses['rework']['label']) ?>»</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div class="field"><label for="kb-rework-reason">چه ایرادی دیدید که باید اصلاح شود؟ (اختیاری؛ به‌صورت یادداشت روی تسک ثبت و برای مسئول فرستاده می‌شود)</label>
        <textarea class="input" id="kb-rework-reason" name="reason" rows="3" maxlength="255" placeholder="<?= e(ph('board_rework')) ?>"></textarea></div></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">برگرداندن برای اصلاح</button></div>
</form></div></div>

<!-- همه‌ی آرشیو -->
<div class="modal" id="m-kb-archive"><div class="modal-box lg">
    <div class="modal-head"><h3><i class="fa-solid fa-box-archive"></i> آرشیو تسک‌ها <span class="badge" id="kb-arch-count"></span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="search mb-1"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="kb-arch-q" placeholder="<?= e(ph('board_archive')) ?>" autocomplete="off" aria-label="جستجو در آرشیو"></div>
        <div id="kb-arch-list" class="list kb-arch-list"></div>
    </div>
</div></div>

<?php if ($canManage) { $this->partial('board_settings', ['scope' => 'project', 'project' => $ppid, 'custom' => $customBoard]); } ?>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $taskProjects, 'assignees' => $assignees, 'shared' => null, 'context' => 'board']); ?>
