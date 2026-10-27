<?php
/** @var array $projects @var array $colleagues @var array $requests @var array $history @var array $permDefs */
$boot = [];
foreach ($projects as $p) {
    $boot[(int) $p['id']] = [
        'id' => (int) $p['id'], 'title' => $p['title'], 'description' => $p['description'], 'max_members' => (int) $p['max_members'], 'priority_type' => $p['priority_type'] ?? 'category',
        'is_creator' => $p['is_creator'], 'perms' => $p['perms'], 'pending' => $p['pending_ids'],
        'members' => array_map(static fn($m) => [
            'id' => (int) $m['user_id'], 'name' => full_name($m), 'mobile' => $m['mobile'], 'avatar' => avatar_url($m['avatar']),
            'is_creator' => (int) $m['user_id'] === (int) $p['user_id'],
            'perms' => \App\Services\Access::decode($m['permissions'], array_keys($permDefs)),
        ], $p['members']),
    ];
}
$projData = ['list' => $boot, 'colleagues' => $colleagues, 'permDefs' => $permDefs, 'me' => (int) $currentUser['id']];
$this->scripts[] = 'projects';
?>
<div class="page-head"><h2><i class="fa-solid fa-diagram-project" style="color:var(--g-400)"></i> پروژه‌ها</h2>
    <button class="btn btn-primary" id="btn-new-project"><i class="fa-solid fa-plus"></i> پروژه جدید</button></div>

<div data-live="proj-body">
<script type="application/json" id="proj-data"><?= json_html($projData) ?></script>
<?php if ($requests): ?>
<div class="card mb-2" style="border-color:rgba(251,191,36,.4)"><div class="card-head"><h3><i class="fa-solid fa-envelope-open-text" style="color:var(--warn)"></i> دعوت‌های عضویت</h3></div>
    <div class="list"><?php foreach ($requests as $r): ?>
        <div class="item"><span class="avatar sq"><?php if ($r['profile_image']): ?><img src="<?= e(project_image_url($r['profile_image'])) ?>" alt=""><?php else: ?><?= e(initial($r['title'])) ?><?php endif; ?></span>
            <div class="grow"><div class="bold"><?= e($r['title']) ?></div><div class="xs muted">دعوت از <?= e(full_name($r)) ?> • <?= e(time_ago($r['created_at'])) ?></div></div>
            <div class="item-actions">
                <form method="post" action="<?= e(url('/projects/requests/' . $r['id'] . '/respond')) ?>" data-ajax class="flex gap-1"><?= csrf_field() ?>
                    <button name="action" value="accept" class="btn btn-sm btn-primary">پذیرفتن</button><button name="action" value="reject" class="btn btn-sm btn-danger">رد</button></form>
            </div></div><?php endforeach; ?></div></div>
<?php endif; ?>

<?php if (!$projects): ?><div class="card empty"><i class="fa-solid fa-diagram-project"></i><p>هنوز در هیچ پروژه‌ای نیستید. یک پروژه بسازید و همکاران را دعوت کنید.</p></div>
<?php else: ?>
<div class="grid grid-auto">
<?php foreach ($projects as $p):
    $pct = (int) $p['tasks_count'] > 0 ? (int) round($p['tasks_done'] / $p['tasks_count'] * 100) : 0; ?>
    <div class="card hover" id="proj-<?= (int) $p['id'] ?>">
        <div class="flex items-center gap-2 mb-1">
            <span class="avatar sq lg" style="width:56px;height:56px;font-size:1.3rem"><?php if ($p['profile_image']): ?><img src="<?= e(project_image_url($p['profile_image'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initial($p['title'])) ?><?php endif; ?></span>
            <div class="grow"><div class="bold"><?= e($p['title']) ?></div>
                <div class="flex gap-1 wrap mt-1"><?php if ($p['is_creator']): ?><span class="badge green">سازنده</span><?php else: ?><span class="badge">عضو</span><?php endif; ?>
                    <?php if ((int) $p['is_blocked_by_me'] > 0): ?><span class="badge red">مسدود</span><?php endif; ?></div></div>
        </div>
        <?php if ($p['description']): ?><p class="muted small" style="max-height:3.4em;overflow:hidden"><?= e($p['description']) ?></p><?php endif; ?>
        <div class="flex between small muted mb-1"><span><i class="fa-solid fa-users"></i> <?= e(fa_num($p['members_count'])) ?> / <?= e(fa_num($p['max_members'])) ?></span><span><?= e(fa_num($p['tasks_done'])) ?> از <?= e(fa_num($p['tasks_count'])) ?> تسک</span></div>
        <div class="progress mb-2"><div style="width:<?= $pct ?>%"></div></div>
        <div class="flex gap-1 wrap">
            <button class="btn btn-sm" data-project="<?= (int) $p['id'] ?>"><i class="fa-solid fa-gear"></i> مدیریت</button>
            <?php if (in_array('view_analytics', $p['perms'], true)): ?><a class="btn btn-sm" href="<?= e(url('/projects/' . $p['id'] . '/analytics')) ?>"><i class="fa-solid fa-chart-line"></i> تحلیل</a><?php endif; ?>
            <?php if (!$p['is_creator']): ?>
                <?php if ((int) $p['is_blocked_by_me'] > 0): ?><button class="btn btn-sm" data-post="<?= e(url('/projects/' . $p['id'] . '/unblock')) ?>">رفع مسدودی</button>
                <?php else: ?><button class="btn btn-sm btn-icon" data-post="<?= e(url('/projects/' . $p['id'] . '/block')) ?>" data-confirm="دیگر دعوتی از این پروژه نمی‌خواهید؟" title="مسدود"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
                <button class="btn btn-sm btn-danger" data-post="<?= e(url('/projects/' . $p['id'] . '/leave')) ?>" data-confirm="از پروژه «<?= e($p['title']) ?>» خارج می‌شوید؟"><i class="fa-solid fa-right-from-bracket"></i> خروج</button>
            <?php else: ?>
                <button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/projects/' . $p['id'] . '/delete')) ?>" data-confirm="پروژه حذف شود؟ تسک‌ها حذف نمی‌شوند ولی از پروژه جدا می‌شوند." title="حذف"><i class="fa-solid fa-trash"></i></button>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($history): ?>
<div class="card mt-3"><div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> پروژه‌های سابق</h3>
    <button class="btn btn-sm btn-danger" data-post="<?= e(url('/projects/history/clear')) ?>" data-confirm="تاریخچه پاک شود؟">پاک‌کردن</button></div>
    <div class="flex wrap gap-1"><?php foreach ($history as $h): ?><span class="chip"><?= e($h['project_title'] ?: 'پروژه‌ی حذف‌شده') ?> <span class="faint xs"><?= e(time_ago($h['left_at'])) ?></span></span><?php endforeach; ?></div></div>
<?php endif; ?>
</div>

<!-- ایجاد / ویرایش -->
<div class="modal" id="m-project"><div class="modal-box">
    <form method="post" action="<?= e(url('/projects')) ?>" enctype="multipart/form-data" data-ajax id="f-project"><?= csrf_field() ?>
        <div class="modal-head"><h3><i class="fa-solid fa-diagram-project"></i> <span id="proj-title-h">پروژه جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <input type="hidden" name="members_present" value="1" id="members-present">
            <div class="field"><label>نام پروژه *</label><input class="input" name="project_title" maxlength="255" required></div>
            <div class="field"><label>توضیحات</label><textarea class="input" name="project_description" rows="3"></textarea></div>
            <div class="row"><div class="field"><label>حداکثر اعضا</label><input class="input" type="number" name="max_members" min="1" max="100" value="10"></div>
                <div class="field"><label>تصویر پروژه</label><input class="input" type="file" name="profile_image" accept="image/*"></div></div>
            <div class="field"><label>نوع اولویت تسک‌ها</label>
                <div class="seg-radio"><?php foreach (\App\Services\Priority::TYPES as $pk => $pl): ?><label><input type="radio" name="priority_type" value="<?= e($pk) ?>" <?= $pk === 'category' ? 'checked' : '' ?>><span><i class="fa-solid <?= $pk === 'percent' ? 'fa-percent' : 'fa-layer-group' ?>"></i> <?= e($pl) ?></span></label><?php endforeach; ?></div>
                <div class="hint">در حالت درصدی، اولویت هنگام ثبت/ویرایش با نوار انتخاب می‌شود (مضرب ۵٪). با تغییر به درصدی: کم = ۲۵٪، متوسط = ۵۰٪، زیاد = ۷۵٪.</div></div>
            <div class="field" id="members-field"><label>اعضا (از همکاران شما)</label><div id="members-list" class="perm-grid" style="max-height:220px;overflow:auto"></div>
                <div class="hint">اگر کاربری تأیید دستی عضویت را فعال کرده باشد، برایش درخواست ارسال می‌شود.</div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div>
    </form></div></div>

<!-- مدیریت اعضا -->
<div class="modal" id="m-members"><div class="modal-box lg"><div class="modal-head"><h3><i class="fa-solid fa-users"></i> <span id="mem-title"></span></h3><button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div id="mem-body"></div></div></div></div>
<div class="modal" id="m-mperm"><div class="modal-box sm"><form id="f-mperm"><div class="modal-head"><h3><i class="fa-solid fa-key"></i> دسترسی <span id="mp-name"></span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div id="mp-list" class="list"></div></div><div class="modal-foot"><button class="btn btn-primary">ذخیره</button></div></form></div></div>
