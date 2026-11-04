<?php
/**
 * تنظیمات ← امنیت: لاگ ورود مدیران به پنل کاربر (App\Core\AdminLog::present).
 * @var array $items @var int $total @var int $pg @var int $pages @var int $per @var array $perOptions @var array $kinds @var string $kind
 * @var array $admins @var array $targets @var string $adminSel @var string $userSel
 */
$this->scripts[] = 'admin';        // ارسال خودکار فیلترها
$this->scripts[] = 'admin_list';   // پنجره‌ی جزئیات کاربر (پروفایل)
$this->boot['adminList'] = ['per' => $perOptions];
$query = array_filter(['admin' => $adminSel, 'u' => $userSel, 'kind' => $kind !== 'all' ? $kind : null, 'per' => $per !== $perOptions[0] ? $per : null]);
$qs = static fn(array $x) => url('/admin/security') . '?' . http_build_query(array_filter($x + $query, static fn($v) => $v !== null && $v !== ''));
$who = static function (?array $p, string $cls = ''): string {
    if ($p === null) {
        return '<span class="muted">کاربر حذف‌شده</span>';
    }
    $av = $p['avatar'] ? '<img src="' . e($p['avatar']) . '" alt="">' : e(initial($p['name']));
    return '<button type="button" class="sl-who ' . $cls . '" data-user-details="' . e($p['id']) . '" title="نمایش پروفایل و جزئیات"><span class="avatar xs">' . $av . '</span><b>' . e($p['name']) . '</b> <span class="ltr small muted">' . e($p['mobile']) . '</span></button>';
};
$icons = ['enter' => ['fa-right-to-bracket', 'green'], 'exit' => ['fa-right-from-bracket', 'muted'], 'create' => ['fa-plus', 'blue'], 'update' => ['fa-pen', 'yellow'], 'delete' => ['fa-trash', 'red']];
?>
<div class="page-head"><div><h2><i class="fa-solid fa-shield-halved" data-css="color:var(--g-400)"></i> امنیت <span class="badge"><?= e(fa_num($total)) ?></span></h2>
    <p class="small muted mt-1">لاگ ورود مدیران به پنل کاربر: زمان ورود و خروج و هر تغییری که مدیر داخل پنل کاربر داده است (با مقدار قبل و بعد).</p></div></div>

<div class="flex between items-center wrap gap-2 mb-2">
    <div class="tabs"><?php foreach ($kinds as $k => $l): ?><a class="tab <?= $kind === $k ? 'active' : '' ?>" href="<?= e($qs(['kind' => $k === 'all' ? null : $k, 'page' => null])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <form method="get" class="flex gap-2 items-center wrap" id="sl-filters">
        <?php if ($kind !== 'all'): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?>
        <label class="flex items-center gap-1 small muted">مدیر
            <select class="input" name="admin" data-autosubmit aria-label="فیلتر مدیر"><option value="">همه‌ی مدیران</option><?php foreach ($admins as $a): ?><option value="<?= e($a['id']) ?>" <?= $a['id'] === $adminSel ? 'selected' : '' ?>><?= e($a['name'] . ' — ' . $a['mobile']) ?></option><?php endforeach; ?></select></label>
        <label class="flex items-center gap-1 small muted">کاربر
            <select class="input" name="u" data-autosubmit aria-label="فیلتر کاربر"><option value="">همه‌ی کاربران</option><?php foreach ($targets as $a): ?><option value="<?= e($a['id']) ?>" <?= $a['id'] === $userSel ? 'selected' : '' ?>><?= e($a['name'] . ' — ' . $a['mobile']) ?></option><?php endforeach; ?></select></label>
        <label class="flex items-center gap-1 small muted">نمایش در هر صفحه
            <select class="input" name="per" data-autosubmit aria-label="تعداد در هر صفحه"><?php foreach ($perOptions as $n): ?><option value="<?= (int) $n ?>" <?= $n === $per ? 'selected' : '' ?>><?= e(fa_num($n)) ?></option><?php endforeach; ?></select></label>
        <?php if ($adminSel !== '' || $userSel !== ''): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/security')) ?>"><i class="fa-solid fa-xmark"></i> حذف فیلتر</a><?php endif; ?>
    </form>
</div>

<div class="sl-list">
<?php foreach ($items as $it): [$ic, $tone] = $icons[$it['action']] ?? ['fa-circle', 'muted']; ?>
    <article class="card sl-item">
        <div class="sl-head">
            <span class="sl-ico tone-<?= e($tone) ?>"><i class="fa-solid <?= e($ic) ?>"></i></span>
            <div class="sl-text">
                <span class="badge purple"><?= e($it['role']) ?></span> <?= $who($it['admin']) ?>
                <?php if ($it['action'] === 'enter'): ?>
                    وارد پنل کاربر <?= $who($it['target']) ?> شد
                <?php elseif ($it['action'] === 'exit'): ?>
                    از پنل کاربر <?= $who($it['target']) ?> خارج شد<?php if ($it['reason']): ?> <span class="small muted">(<?= e($it['reason']) ?>)</span><?php endif; ?>
                <?php else: ?>
                    در پنل کاربر <?= $who($it['target']) ?>، <?= e($it['entity']['type']) ?>
                    <?php if ($it['link']): ?><button type="button" class="sl-entity" data-impersonate="<?= e($it['link']['user']) ?>" data-to="<?= e($it['link']['to']) ?>" title="<?= e($it['link']['title']) ?> (تب جدید)">«<?= e($it['entity']['title'] ?: '—') ?>» <i class="fa-solid fa-arrow-up-right-from-square xs"></i></button>
                    <?php else: ?><b>«<?= e($it['entity']['title'] ?: '—') ?>»</b><?php endif; ?>
                    را <?= e($it['verb']) ?>
                <?php endif; ?>
            </div>
            <time class="sl-time small muted nowrap" title="<?= e($it['at_full']) ?>"><i class="fa-regular fa-clock"></i> <?= e($it['at_full']) ?></time>
        </div>
        <?php if ($it['changes']): ?>
        <div class="table-wrap sl-changes"><table class="table table-sm"><thead><tr><th>پارامتر</th><?php if ($it['action'] !== 'create'): ?><th>قبل</th><?php endif; ?><?php if ($it['action'] !== 'delete'): ?><th>بعد</th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($it['changes'] as $c): ?>
            <tr><td class="bold nowrap"><?= e($c['label']) ?></td>
                <?php if ($it['action'] !== 'create'): ?><td class="sl-before"><?= e((string) $c['before']) ?></td><?php endif; ?>
                <?php if ($it['action'] !== 'delete'): ?><td class="sl-after"><?= e((string) $c['after']) ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
<?php if (!$items): ?><div class="card empty"><i class="fa-solid fa-shield-halved"></i><p><?= $adminSel !== '' || $userSel !== '' || $kind !== 'all' ? 'رکوردی با این فیلتر پیدا نشد.' : 'هنوز هیچ مدیری وارد پنل کاربری نشده است.' ?></p></div><?php endif; ?>
</div>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => $query]); ?>
<?php $this->partial('admin_user_modals'); ?>
