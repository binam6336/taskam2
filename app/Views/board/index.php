<?php
/** @var array $columns @var array $statuses @var array $projects @var array $filters */
$this->scripts[] = 'board';
$total = array_sum(array_map('count', $columns));
?>
<div class="page-head"><h2><i class="fa-solid fa-table-columns" style="color:var(--g-400)"></i> برد تیمی <span class="badge yellow">دمو</span></h2>
    <a class="btn btn-primary" href="<?= e(url('/tasks')) ?>"><i class="fa-solid fa-plus"></i> تسک جدید</a></div>

<form class="kb-filters card" method="get" action="<?= e(url('/board')) ?>">
    <select class="input" name="project" data-autosubmit>
        <option value="0">همه‌ی پروژه‌ها</option>
        <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $filters['project'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?>
    </select>
    <div class="search kb-q"><i class="fa-solid fa-magnifying-glass"></i><input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="جستجو در عنوان تسک‌ها…"></div>
    <label class="check kb-mine"><input type="checkbox" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?> data-autosubmit><div><b>فقط مسئولیت من</b></div></label>
    <button class="btn"><i class="fa-solid fa-magnifying-glass"></i> اعمال</button>
</form>

<div data-live="board-body">
<?php if ($total === 0): ?>
<div class="card empty"><i class="fa-solid fa-table-columns"></i><p>تسکی برای نمایش در برد نیست. این‌جا تسک‌های خودتان و تسک‌های اعضایی که مدیر پروژه در «مشاهده‌ی تسک‌های» برایتان انتخاب کرده نمایش داده می‌شوند.</p></div>
<?php endif; ?>
<div class="kb-board" id="kb-board">
<?php foreach ($statuses as $key => $s): $cards = $columns[$key]; ?>
    <section class="kb-col tone-<?= e($s['tone']) ?>" data-col="<?= e($key) ?>" aria-label="<?= e($s['label']) ?>">
        <header class="kb-col-head" title="<?= e($s['desc']) ?>">
            <i class="fa-solid <?= e($s['icon']) ?>"></i>
            <div class="grow"><b><?= e($s['label']) ?></b><span><?= e($s['en']) ?></span></div>
            <span class="kb-count"><?= e(fa_num(count($cards))) ?></span>
        </header>
        <div class="kb-list" data-drop="<?= e($key) ?>">
        <?php foreach ($cards as $t):
            $overdue = $key !== 'done' && $t['due_date'] && strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59')) < time(); ?>
            <article class="kb-card p-<?= e($t['priority']) ?><?= $t['is_focus'] ? ' kb-focus' : '' ?>" id="kb-task-<?= (int) $t['id'] ?>" data-id="<?= (int) $t['id'] ?>" data-status="<?= e($key) ?>" data-moves="<?= e(implode(' ', $t['moves'])) ?>" <?= $t['can_move'] ? 'draggable="true"' : 'data-locked' ?>>
                <?php if ($t['is_focus']): ?><div class="kb-focus-tag" title="تسک انتخاب‌شده" aria-label="تسک انتخاب‌شده"><i class="fa-solid fa-location-crosshairs"></i></div><?php endif; ?>
                <div class="kb-card-top">
                    <a class="kb-title" href="<?= e(url('/tasks?task=' . $t['token'])) ?>"><?= e($t['title']) ?></a>
                    <?php if ($t['can_move']): ?><button type="button" class="btn btn-ghost btn-icon btn-xs" data-kb-menu title="انتقال به…"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <?php else: ?><span class="faint xs" title="دسترسی جابه‌جایی این تسک در برد را ندارید"><i class="fa-solid fa-lock"></i></span><?php endif; ?>
                </div>
                <?php if ($key === 'blocked' && $t['blocked_reason']): ?><div class="kb-reason"><i class="fa-solid fa-circle-exclamation"></i> <?= e($t['blocked_reason']) ?></div><?php endif; ?>
                <div class="kb-meta">
                    <?= priority_badge($t) ?>
                    <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i> <?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $overdue ? ' (گذشته)' : '' ?></span><?php endif; ?>
                    <?php if ((int) $t['notes_count'] > 0): ?><span><i class="fa-regular fa-comment"></i> <?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                    <?php if ((int) $t['attachments_count'] > 0): ?><span><i class="fa-solid fa-paperclip"></i> <?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
                </div>
                <div class="kb-foot">
                    <?php if ($t['project_title'] && !$filters['project']): ?><span class="kb-proj"><i class="fa-solid fa-diagram-project"></i> <?= e($t['project_title']) ?></span><?php else: ?><span></span><?php endif; ?>
                    <?php if ($t['assignee_id']): $an = full_name($t, 'assignee_'); ?>
                        <span class="avatar sm" title="مسئول: <?= e($an) ?>"><?php if ($t['assignee_avatar']): ?><img src="<?= e(avatar_url($t['assignee_avatar'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initial($an)) ?><?php endif; ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
</div>

<script type="application/json" id="kb-data"><?= json_html(['statuses' => array_map(static fn($s) => ['label' => $s['label'], 'icon' => $s['icon']], $statuses)]) ?></script>

<!-- علت توقف -->
<div class="modal" id="m-kb-block"><div class="modal-box sm"><form id="f-kb-block">
    <div class="modal-head"><h3><i class="fa-solid fa-ban" style="color:var(--danger)"></i> علت توقف</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div class="field"><label>چرا این تسک متوقف شده؟ (اختیاری)</label>
        <textarea class="input" name="reason" rows="3" maxlength="255" placeholder="مثلاً: منتظر تأیید طراحی / وابسته به تسک دیگر / مشکل فنی سرور"></textarea></div></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-danger">انتقال به مسدودشده</button></div>
</form></div></div>
