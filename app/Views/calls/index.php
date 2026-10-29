<?php
/** @var array $calls @var int $total @var string $tab @var int $pg @var int $pages @var string $q @var array $counts @var ?array $shared @var array $assignees */
$this->boot['calls'] = ['shared' => $shared ? ['id' => (int) $shared['id'], 'html' => true] : null];
$this->scripts[] = 'calls';
$url = static fn(string $t) => url('/calls') . '?' . http_build_query(['tab' => $t]);
$edit = static fn(array $c) => json_html(['first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'mobile' => $c['mobile'], 'email' => $c['email'], 'store' => $c['store'], 'website' => $c['website'], 'description' => $c['description'], 'assignee_id' => $c['assignee_id']]);
$card = static function (array $c) use ($edit): void { ?>
    <div class="card hover" id="call-<?= (int) $c['id'] ?>">
        <div class="flex between items-center gap-1 mb-1">
            <div class="flex items-center gap-2"><span class="avatar"><?= e(initial($c['first_name'])) ?></span>
                <div><div class="bold"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div><a class="small ltr" href="tel:<?= e($c['mobile']) ?>"><?= e($c['mobile']) ?></a></div></div>
            <span class="badge <?= (int) $c['status'] === 1 ? 'green' : 'yellow' ?>"><?= (int) $c['status'] === 1 ? 'انجام‌شده' : 'جدید' ?></span>
        </div>
        <div class="item-meta" style="margin:0 0 .5rem">
            <?php if ($c['store']): ?><span><i class="fa-solid fa-store"></i><?= e($c['store']) ?></span><?php endif; ?>
            <?php if ($c['email']): ?><span class="ltr"><i class="fa-regular fa-envelope"></i><?= e($c['email']) ?></span><?php endif; ?>
            <?php if ($c['website']): ?><span class="ltr"><i class="fa-solid fa-globe"></i><?= e($c['website']) ?></span><?php endif; ?>
            <span><i class="fa-regular fa-clock"></i><?= e(jdate($c['created_at'])) ?></span>
        </div>
        <?php if ($c['description']): ?><p class="muted small note-body"><?= e($c['description']) ?></p><?php endif; ?>
        <div class="flex between items-center wrap gap-1 mt-1">
            <span class="xs muted"><i class="fa-solid fa-user-check"></i> مسئول: <?= e($c['assignee_id'] ? full_name($c, 'assignee_') : '—') ?> • ثبت: <?= e(full_name($c, 'creator_')) ?></span>
            <div class="flex gap-1">
                <?php if ((int) $c['status'] === 0 && ($c['can']['complete'] ?? false)): ?><button class="btn btn-sm btn-primary" data-post="<?= e(url('/calls/' . $c['id'] . '/complete')) ?>" data-confirm="این درخواست انجام‌شده ثبت شود؟" data-danger="0"><i class="fa-solid fa-check"></i> انجام شد</button><?php endif; ?>
                <button class="btn btn-sm btn-icon" data-share-call="<?= (int) $c['id'] ?>" title="کپی لینک"><i class="fa-solid fa-link"></i></button>
                <?php if ($c['can']['edit'] ?? false): ?><button class="btn btn-sm btn-icon" data-edit='<?= e($edit($c)) ?>' data-target="m-call" data-action="/calls/<?= (int) $c['id'] ?>/update"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
                <?php if ($c['can']['delete'] ?? false): ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/calls/' . $c['id'] . '/delete')) ?>" data-confirm="درخواست حذف شود؟"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </div>
        </div>
    </div>
<?php };
?>
<div class="page-head">
    <div class="tabs" data-live="c-tabs">
        <a class="tab <?= $tab === 'new' ? 'active' : '' ?>" href="<?= e($url('new')) ?>">جدید <span class="badge-count"><?= e(fa_num($counts['new'])) ?></span></a>
        <a class="tab <?= $tab === 'done' ? 'active' : '' ?>" href="<?= e($url('done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($counts['done'])) ?></span></a>
    </div>
    <button class="btn btn-primary" id="btn-new-call"><i class="fa-solid fa-plus"></i> درخواست جدید</button>
</div>
<form method="get" class="search mb-2" style="max-width:420px"><input type="hidden" name="tab" value="<?= e($tab) ?>"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('calls_search')) ?>"></form>
<div data-live="c-list">
<?php if (!$calls): ?><div class="card empty"><i class="fa-solid fa-phone-volume"></i><p>درخواستی وجود ندارد.</p></div>
<?php else: ?><div class="grid grid-auto"><?php foreach ($calls as $c) { $card($c); } ?></div>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => ['tab' => $tab, 'q' => $q]]); endif; ?>
</div>

<?php if ($shared): ?><div class="modal open" id="m-shared"><div class="modal-box sm"><div class="modal-body">
    <div class="flex between mb-2"><h3 style="margin:0">درخواست تماس</h3><button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <?php $card($shared); ?></div></div></div><?php endif; ?>

<div class="modal" id="m-call"><div class="modal-box">
    <form method="post" action="<?= e(url('/calls')) ?>" data-ajax id="f-call"><?= csrf_field() ?>
        <div class="modal-head"><h3><i class="fa-solid fa-phone-volume"></i> درخواست تماس</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="row"><div class="field"><label>نام *</label><input class="input" name="first_name" maxlength="150" required></div><div class="field"><label>نام خانوادگی *</label><input class="input" name="last_name" maxlength="150" required></div></div>
            <div class="row"><div class="field"><label>موبایل *</label><input class="input ltr" name="mobile" data-mobile inputmode="numeric" placeholder="<?= e(ph('calls_mobile')) ?>" required></div><div class="field"><label>ایمیل</label><input class="input ltr" type="email" name="email"></div></div>
            <div class="row"><div class="field"><label>فروشگاه</label><input class="input" name="store" maxlength="255"></div><div class="field"><label>وب‌سایت</label><input class="input ltr" name="website" maxlength="255"></div></div>
            <div class="field"><label>مسئول پیگیری</label><select class="input" name="assignee_id"><option value="">خودم</option><?php foreach ($assignees as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>توضیحات</label><textarea class="input" name="description" rows="3"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div>
    </form></div></div>
