<?php
/** مدیریت تیم ← دپارتمان / گروه (v5.10؛ فلوی ساخت دومرحله‌ای v5.11)
 * @var array $projects @var int $current @var string $q @var array $groups @var array $manage @var array $analytics @var array $manageable @var int $total */
$this->scripts[] = 'groups';
$me = (int) (\App\Core\Auth::user()['id'] ?? 0);
?>
<div class="page-head">
    <h2><i class="fa-solid fa-people-group" data-css="color:var(--g-400)"></i> دپارتمان / گروه</h2>
    <?php if ($manageable): ?><button type="button" class="btn btn-primary" id="btn-new-group"><i class="fa-solid fa-plus"></i> گروه جدید</button><?php endif; ?>
</div>
<p class="muted small mb-2 grp-intro">هر گروه مال یک پروژه است و می‌شود تسک را مثل یک نفر به آن داد؛ همه‌ی اعضا تسک را می‌بینند و انجامش می‌دهند.
    اگر گروه سرگروه داشته باشد، هنگام ساخت تسک می‌شود تعیین کرد که تحویل فقط با تیک سرگروه باشد. ساخت و ویرایش گروه با کسانی است که در پروژه دسترسی «افزودن کاربر» دارند.</p>

<?php if (!$projects): ?>
    <div class="card empty"><i class="fa-solid fa-diagram-project"></i><p>اول یک پروژه بسازید یا عضو پروژه‌ای شوید؛ گروه‌ها داخل پروژه ساخته می‌شوند.</p><a class="btn btn-primary" href="<?= e(url('/projects')) ?>">پروژه‌ها</a></div>
<?php else: ?>
<form method="get" class="grp-filter mb-2" action="<?= e(url('/groups')) ?>" role="search" aria-label="فیلتر گروه‌ها">
    <div class="search grow"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('groups_search')) ?>" aria-label="جستجو در نام گروه"></div>
    <select class="input" name="project" data-logo-select data-autosubmit aria-label="فیلتر پروژه">
        <option value="">همه‌ی پروژه‌ها</option>
        <?php foreach ($projects as $p): ?><option value="<?= e(pid('projects', $p)) ?>" data-img="<?= e((string) project_image_url($p['profile_image'] ?? null)) ?>" <?= (int) $p['id'] === $current ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($current || $q !== ''): ?><a class="btn" href="<?= e(url('/groups')) ?>"><i class="fa-solid fa-xmark"></i> حذف فیلتر</a><?php endif; ?>
</form>

<div data-live="groups">
<?php if (!$groups): ?>
    <div class="card empty"><i class="fa-solid fa-people-group"></i><p><?= $current || $q !== '' ? 'گروهی با این فیلتر پیدا نشد.' : 'هنوز گروهی ساخته نشده است.' . ($manageable ? ' با «گروه جدید» اولین گروه را بسازید.' : ' ساخت گروه با مدیر پروژه است.') ?></p></div>
<?php else: ?>
<p class="xs faint mb-1"><?= e(fa_num(count($groups))) ?> گروه<?= $current || $q !== '' ? ' از ' . e(fa_num($total)) : '' ?></p>
<div class="grp-grid">
    <?php foreach ($groups as $g): $gp = pid('project_groups', $g); $pp = pid('projects', (int) $g['project_id']); $head = null; $canM = !empty($manage[(int) $g['project_id']]);
        foreach ($g['members'] as $m) { if ((int) $m['id'] === (int) $g['head_id']) { $head = $m; } } ?>
    <article class="card grp-card" id="grp-<?= e($gp) ?>">
        <div class="grp-head">
            <span class="grp-ic" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
            <div class="grow min0"><h3 class="truncate"><?= e($g['title']) ?></h3>
                <div class="xs muted truncate"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> <?= e($g['project_title']) ?></div>
                <?php if ($g['description']): ?><p class="xs muted grp-desc"><?= e($g['description']) ?></p><?php endif; ?></div>
            <?php if ($canM): ?>
            <div class="flex gap-1">
                <button type="button" class="btn btn-sm btn-icon" data-edit-group="<?= e($gp) ?>" data-group='<?= e(json_html(['id' => $gp, 'project' => $pp, 'title' => $g['title'], 'description' => (string) $g['description'], 'head_id' => $g['head_id'] !== null ? pid('users', (int) $g['head_id']) : null, 'members' => array_map(static fn($m) => pid('users', (int) $m['id']), $g['members'])])) ?>' title="ویرایش گروه" aria-label="ویرایش گروه «<?= e($g['title']) ?>»"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/groups/' . $gp . '/delete')) ?>" data-confirm="گروه «<?= e($g['title']) ?>» حذف شود؟ (فقط گروهی که هیچ تسکی ندارد حذف می‌شود)" title="حذف گروه" aria-label="حذف گروه «<?= e($g['title']) ?>»"><i class="fa-solid fa-trash"></i></button>
            </div>
            <?php endif; ?>
        </div>
        <div class="grp-meta small">
            <span><i class="fa-solid fa-crown"></i> سرگروه: <?= $head ? '<b>' . e(full_name($head)) . '</b>' : '<span class="faint">ندارد</span>' ?></span>
            <?php // عضو گروه: «وظایف من» با فیلتر گروه؛ دارنده‌ی تحلیل پروژه: همه‌ی تسک‌های پروژه با فیلتر گروه
            $isMem = in_array($me, array_map(static fn($m) => (int) $m['id'], $g['members']), true);
            $tq = $isMem ? ['scope' => 'mine', 'group' => $gp, 'status' => 'open'] : (!empty($analytics[(int) $g['project_id']]) ? ['scope' => 'project', 'project' => $pp, 'group' => $gp, 'status' => 'open'] : null); ?>
            <?php if ($tq): ?><a href="<?= e(url('/tasks') . '?' . http_build_query($tq)) ?>" title="تسک‌های باز این گروه"><i class="fa-solid fa-list-check"></i> <?= e(fa_num($g['open_tasks'])) ?> تسک باز</a>
            <?php else: ?><span><i class="fa-solid fa-list-check"></i> <?= e(fa_num($g['open_tasks'])) ?> تسک باز</span><?php endif; ?>
        </div>
        <ul class="grp-members" aria-label="اعضای گروه">
            <?php foreach ($g['members'] as $m): $isHead = (int) $m['id'] === (int) $g['head_id']; ?>
            <li class="grp-m<?= $isHead ? ' is-head' : '' ?>"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar'] ?? null), 'size' => 'sm']); ?><span class="truncate"><?= e(full_name($m)) ?></span><?php if ($isHead): ?><i class="fa-solid fa-crown grp-crown" title="سرگروه" aria-label="سرگروه"></i><?php endif; ?></li>
            <?php endforeach; ?>
        </ul>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($manageable): ?>
<?php // گروه جدید (v5.11): مرحله‌ی ۱ فقط انتخاب پروژه؛ مرحله‌ی ۲ نام، اعضای همان پروژه با پروفایل و یک سرگروه از همان فهرست ?>
<div class="modal" id="m-group-proj"><div class="modal-box sm">
    <div class="modal-head"><h3><i class="fa-solid fa-diagram-project"></i> گروه جدید — انتخاب پروژه</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <p class="small muted mb-1">گروه در کدام پروژه ساخته شود؟ اعضای گروه از اعضای همین پروژه انتخاب می‌شوند.</p>
        <div class="grp-projs" role="radiogroup" aria-label="پروژه‌ی گروه">
            <?php foreach ($manageable as $i => $p): ?>
            <label class="grp-proj-opt"><input type="radio" name="grp_project" value="<?= e(pid('projects', $p)) ?>" data-title="<?= e($p['title']) ?>"<?= (int) $p['id'] === $current || ($i === 0 && !$current) ? ' checked' : '' ?>>
                <span class="avatar sm sq"><?php if (!empty($p['profile_image'])): ?><img src="<?= e(project_image_url($p['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial($p['title'])) ?><?php endif; ?></span>
                <span class="grow min0"><b class="truncate"><?= e($p['title']) ?></b><small class="faint"><?= e(fa_num(max(1, (int) $p['members_count']))) ?> عضو</small></span></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button type="button" class="btn btn-primary" id="grp-next">ادامه <i class="fa-solid fa-arrow-left"></i></button></div>
</div></div>

<div class="modal" id="m-group"><div class="modal-box">
    <form method="post" action="<?= e(url('/groups')) ?>" data-ajax data-after="reload" id="f-group"><?= csrf_field() ?>
        <input type="hidden" name="project_id" value="" data-keep>
        <div class="modal-head"><h3><i class="fa-solid fa-people-group"></i> <span id="m-group-title">گروه جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="grp-proj-line small mb-2"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> پروژه: <b id="grp-proj-name"></b>
                <button type="button" class="btn btn-ghost btn-sm" id="grp-change-proj"><i class="fa-solid fa-arrow-right"></i> تغییر پروژه</button></div>
            <div class="field"><label for="grp-title">نام گروه / دپارتمان <span class="req" aria-hidden="true">*</span></label><input class="input" id="grp-title" name="title" maxlength="80" required placeholder="<?= e(ph('group_title')) ?>"></div>
            <div class="field"><label for="grp-desc">توضیح (اختیاری)</label><input class="input" id="grp-desc" name="description" maxlength="255" placeholder="<?= e(ph('group_desc')) ?>"></div>
            <div class="field"><div class="flex between items-center gap-1 wrap"><label>اعضا و سرگروه <span class="req" aria-hidden="true">*</span></label><span class="xs faint" id="grp-count" aria-live="polite"></span></div>
                <div class="search mb-1"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="input" id="grp-mem-q" placeholder="<?= e(ph('group_member_search')) ?>" aria-label="جستجو در اعضای پروژه"></div>
                <div class="grp-mlist" id="grp-pick" role="group" aria-label="اعضای پروژه"></div>
                <small class="faint">تیک = عضو گروه؛ <i class="fa-solid fa-crown"></i> = سرگروه (فقط یک نفر، اختیاری). سرگروه می‌تواند تحویل تسک‌های گروه را بر عهده بگیرد.</small></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div>
    </form></div></div>
<?php endif; ?>
