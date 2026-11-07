<?php
/** مدیریت تیم ← دپارتمان / گروه (v5.10)
 * @var array $boot @var array $projects @var int $current @var array $groups @var bool $canManage @var int $memberCount */
$this->scripts[] = 'groups';
$this->boot['groups'] = $boot;   // داده‌ی فرم ساخت/ویرایش (groups.js)
$cp = $current ? pid('projects', $current) : '';
$me = (int) (\App\Core\Auth::user()['id'] ?? 0);
$canAnalytics = $current && \App\Services\Access::projectCan($current, $me, 'view_analytics');
?>
<div class="page-head">
    <h2><i class="fa-solid fa-people-group" data-css="color:var(--g-400)"></i> دپارتمان / گروه</h2>
    <?php if ($canManage): ?><button type="button" class="btn btn-primary" id="btn-new-group"><i class="fa-solid fa-plus"></i> گروه جدید</button><?php endif; ?>
</div>
<p class="muted small mb-2 grp-intro">هر گروه مال یک پروژه است و می‌شود تسک را مثل یک نفر به آن داد؛ همه‌ی اعضا تسک را می‌بینند و انجامش می‌دهند.
    اگر گروه سرگروه داشته باشد، هنگام ساخت تسک می‌شود تعیین کرد که تحویل فقط با تیک سرگروه باشد. ساخت و ویرایش گروه با کسانی است که در پروژه دسترسی «افزودن کاربر» دارند.</p>

<?php if (!$projects): ?>
    <div class="card empty"><i class="fa-solid fa-diagram-project"></i><p>اول یک پروژه بسازید یا عضو پروژه‌ای شوید؛ گروه‌ها داخل پروژه ساخته می‌شوند.</p><a class="btn btn-primary" href="<?= e(url('/projects')) ?>">پروژه‌ها</a></div>
<?php else: ?>
<form method="get" class="grp-proj mb-2" action="<?= e(url('/groups')) ?>">
    <label class="small muted" for="grp-project">پروژه</label>
    <select class="input" id="grp-project" name="project" data-logo-select data-autosubmit>
        <?php foreach ($projects as $p): ?><option value="<?= e(pid('projects', $p)) ?>" data-img="<?= e((string) project_image_url($p['profile_image'] ?? null)) ?>" <?= (int) $p['id'] === $current ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?>
    </select>
    <span class="xs faint"><?= e(fa_num($memberCount)) ?> عضو در پروژه</span>
</form>

<div data-live="groups">
<?php if (!$groups): ?>
    <div class="card empty"><i class="fa-solid fa-people-group"></i><p>این پروژه هنوز گروهی ندارد.<?= $canManage ? ' با «گروه جدید» اولین گروه را بسازید.' : ' ساخت گروه با مدیر پروژه است.' ?></p></div>
<?php else: ?>
<div class="grp-grid">
    <?php foreach ($groups as $g): $gp = pid('project_groups', $g); $head = null;
        foreach ($g['members'] as $m) { if ((int) $m['id'] === (int) $g['head_id']) { $head = $m; } } ?>
    <article class="card grp-card" id="grp-<?= e($gp) ?>">
        <div class="grp-head">
            <span class="grp-ic" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
            <div class="grow min0"><h3 class="truncate"><?= e($g['title']) ?></h3>
                <?php if ($g['description']): ?><p class="xs muted grp-desc"><?= e($g['description']) ?></p><?php endif; ?></div>
            <?php if ($canManage): ?>
            <div class="flex gap-1">
                <button type="button" class="btn btn-sm btn-icon" data-edit-group="<?= e($gp) ?>" data-group='<?= e(json_html(['id' => $gp, 'title' => $g['title'], 'description' => (string) $g['description'], 'head_id' => $g['head_id'] !== null ? pid('users', (int) $g['head_id']) : null, 'members' => array_map(static fn($m) => pid('users', (int) $m['id']), $g['members'])])) ?>' title="ویرایش گروه" aria-label="ویرایش گروه «<?= e($g['title']) ?>»"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/groups/' . $gp . '/delete')) ?>" data-confirm="گروه «<?= e($g['title']) ?>» حذف شود؟ (فقط گروهی که هیچ تسکی ندارد حذف می‌شود)" title="حذف گروه" aria-label="حذف گروه «<?= e($g['title']) ?>»"><i class="fa-solid fa-trash"></i></button>
            </div>
            <?php endif; ?>
        </div>
        <div class="grp-meta small">
            <span><i class="fa-solid fa-crown"></i> سرگروه: <?= $head ? '<b>' . e(full_name($head)) . '</b>' : '<span class="faint">ندارد</span>' ?></span>
            <?php // عضو گروه: «وظایف من» با فیلتر گروه؛ دارنده‌ی تحلیل پروژه: همه‌ی تسک‌های پروژه با فیلتر گروه
            $isMem = in_array($me, array_map(static fn($m) => (int) $m['id'], $g['members']), true);
            $tq = $isMem ? ['scope' => 'mine', 'group' => $gp, 'status' => 'open'] : ($canAnalytics ? ['scope' => 'project', 'project' => $cp, 'group' => $gp, 'status' => 'open'] : null); ?>
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

<?php if ($canManage): ?>
<div class="modal" id="m-group"><div class="modal-box">
    <form method="post" action="<?= e(url('/groups')) ?>" data-ajax data-after="reload" id="f-group"><?= csrf_field() ?>
        <input type="hidden" name="project_id" value="<?= e($cp) ?>" data-keep>
        <div class="modal-head"><h3><i class="fa-solid fa-people-group"></i> <span id="m-group-title">گروه جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="field"><label for="grp-title">نام گروه / دپارتمان <span class="req" aria-hidden="true">*</span></label><input class="input" id="grp-title" name="title" maxlength="80" required placeholder="<?= e(ph('group_title')) ?>"></div>
            <div class="field"><label for="grp-desc">توضیح (اختیاری)</label><input class="input" id="grp-desc" name="description" maxlength="255" placeholder="<?= e(ph('group_desc')) ?>"></div>
            <div class="field"><label>اعضا <span class="req" aria-hidden="true">*</span> <small class="faint">فقط اعضای همین پروژه</small></label>
                <div class="grp-pick" id="grp-pick" role="group" aria-label="اعضای گروه"></div></div>
            <div class="field"><label for="grp-head">سرگروه (اختیاری)</label>
                <select class="input" id="grp-head" name="head_id"><option value="">بدون سرگروه</option></select>
                <small class="faint">سرگروه می‌تواند تحویل تسک‌های گروه را بر عهده بگیرد (اگر سازنده‌ی تسک «تأیید با سرگروه» را بزند).</small></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div>
    </form></div></div>
<?php endif; ?>
