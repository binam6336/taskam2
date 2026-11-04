<?php
/**
 * فهرست فقط‌خواندنی مدیر (از کارت‌های «تحلیل سیستم»): تسک‌ها، پروژه‌ها، درخواست‌های تماس — صفحه‌بندی، جستجو و فیلتر وضعیت.
 * @var string $kind @var array $rows @var int $total @var int $pg @var int $pages @var int $per @var array $perOptions @var string $q @var string $status @var array $filters @var string $heading @var string $icon
 */
$this->scripts[] = 'admin';   // جستجوی خودکار (#users-tools)
$this->scripts[] = 'admin_list';   // پنجره‌ی جزئیات پروژه (اعضا و تسک‌ها با صفحه‌بندی)
$this->boot['adminList'] = ['per' => $perOptions];
$st = \App\Services\BoardService::STATUSES;
$pc = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
$base = '/admin/list/' . $kind;
$qs = static fn(array $x) => url($base) . '?' . http_build_query(array_filter($x + ['q' => $q, 'per' => $per !== $perOptions[0] ? $per : null, 'status' => $status !== 'all' ? $status : null], static fn($v) => $v !== null && $v !== ''));
$name = static fn(?string $f, ?string $l) => trim(($f ?? '') . ' ' . ($l ?? '')) ?: '—';
?>
<div class="page-head"><div><a class="small muted" href="<?= e(url('/admin/analysis')) ?>"><i class="fa-solid fa-arrow-right"></i> تحلیل سیستم</a>
    <h2 class="mt-1"><i class="fa-solid <?= e($icon) ?>" data-css="color:var(--g-400)"></i> <?= e($heading) ?> <span class="badge"><?= e(fa_num($total)) ?></span></h2></div></div>

<div class="flex between items-center wrap gap-2 mb-2">
    <div class="tabs"><?php foreach ($filters as $k => $l): ?><a class="tab <?= $status === $k ? 'active' : '' ?>" href="<?= e($qs(['status' => $k === 'all' ? null : $k, 'page' => null])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <form method="get" class="flex gap-2 items-center wrap" id="users-tools">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="search" data-css="min-width:260px"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('admin_list_search')) ?>" aria-label="جستجو"></div>
        <label class="flex items-center gap-1 small muted users-per">نمایش در هر صفحه
            <select class="input" name="per" data-autosubmit aria-label="تعداد در هر صفحه"><?php foreach ($perOptions as $n): ?><option value="<?= (int) $n ?>" <?= $n === $per ? 'selected' : '' ?>><?= e(fa_num($n)) ?></option><?php endforeach; ?></select></label>
    </form>
</div>

<div class="table-wrap"><table class="table"><thead><tr>
<?php if ($kind === 'tasks'): ?><th>تسک</th><th>پروژه</th><th>مسئول</th><th>سازنده</th><th>وضعیت</th><th>اولویت</th><th>سررسید</th><th>ایجاد</th>
<?php elseif ($kind === 'projects'): ?><th>پروژه</th><th>سازنده</th><th>اعضا</th><th>تسک‌ها</th><th>وضعیت</th><th>ایجاد</th>
<?php else: ?><th>مخاطب</th><th>موبایل</th><th>فروشگاه</th><th>مسئول</th><th>ثبت‌کننده</th><th>وضعیت</th><th>ایجاد</th><?php endif; ?>
</tr></thead><tbody>
<?php foreach ($rows as $x): ?>
<?php if ($kind === 'tasks'):
    $s = $x['blocked_at'] ? ['label' => 'مسدود', 'tone' => 'danger', 'icon' => 'fa-ban'] : ($x['archived_at'] ? ['label' => 'آرشیو', 'tone' => 'muted', 'icon' => 'fa-box-archive'] : ($st[$x['board_status']] ?? ['label' => $x['is_completed'] ? 'انجام‌شده' : 'باز', 'tone' => 'muted', 'icon' => 'fa-circle']));
    $late = !$x['is_completed'] && $x['due_date'] && $x['due_date'] < date('Y-m-d'); ?>
    <tr><td class="bold"><?= e($x['title']) ?></td><td class="small"><?= e($x['project_title'] ?? '—') ?></td><td class="small"><?= e($name($x['a_first'], $x['a_last'])) ?></td><td class="small muted"><?= e($name($x['c_first'], $x['c_last'])) ?></td>
        <td><span class="badge board-st tone-<?= e($s['tone']) ?>"><i class="fa-solid <?= e($s['icon']) ?>"></i> <?= e($s['label']) ?></span></td>
        <td><?php if ($x['priority_percent'] !== null): ?><span class="badge"><?= e(fa_num((int) $x['priority_percent'])) ?>٪</span><?php else: ?><span class="badge <?= e($pc[$x['priority']][1]) ?>"><?= e($pc[$x['priority']][0]) ?></span><?php endif; ?></td>
        <td class="small <?= $late ? 'prio-high' : '' ?>"><?= $x['due_date'] ? e(jdate($x['due_date'] . ' 00:00:00', false)) . ($late ? ' (عقب‌افتاده)' : '') : '—' ?></td><td class="small muted nowrap"><?= e(jdate((string) $x['created_at'])) ?></td></tr>
<?php elseif ($kind === 'projects'): ?>
    <tr class="al-row" data-aproj="<?= e(pid('projects', $x)) ?>" tabindex="0" role="button" title="نمایش جزئیات پروژه"><td class="bold"><i class="fa-solid fa-circle-info faint xs"></i> <?= e($x['title']) ?></td><td class="small"><?= e($name($x['first_name'], $x['last_name'])) ?></td><td class="small"><?= e(fa_num((int) $x['members'])) ?> نفر</td>
        <td class="small"><?= e(fa_num((int) $x['done'])) ?> انجام‌شده از <?= e(fa_num((int) $x['tasks'])) ?></td>
        <td><?= $x['blocked_at'] ? '<span class="badge red"><i class="fa-solid fa-ban"></i> مسدود</span>' : '<span class="badge green">فعال</span>' ?></td><td class="small muted nowrap"><?= e(jdate((string) $x['created_at'])) ?></td></tr>
<?php else: ?>
    <tr><td class="bold"><?= e($name($x['first_name'], $x['last_name'])) ?></td><td class="small ltr"><?= e(mask_mobile($x['mobile'])) ?></td><td class="small"><?= e($x['store'] ?: '—') ?></td>
        <td class="small"><?= e($name($x['a_first'], $x['a_last'])) ?></td><td class="small muted"><?= e($name($x['c_first'], $x['c_last'])) ?></td>
        <td><?= $x['blocked_at'] ? '<span class="badge red"><i class="fa-solid fa-ban"></i> مسدود</span>' : ((int) $x['status'] === 1 ? '<span class="badge green">انجام‌شده</span>' : '<span class="badge yellow">جدید</span>') ?></td><td class="small muted nowrap"><?= e(jdate((string) $x['created_at'])) ?></td></tr>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" class="center muted"><?= $q !== '' ? 'موردی با این جستجو پیدا نشد.' : 'موردی وجود ندارد.' ?></td></tr><?php endif; ?>
</tbody></table>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => array_filter(['q' => $q, 'per' => $per !== $perOptions[0] ? $per : null, 'status' => $status !== 'all' ? $status : null])]); ?></div>

<?php if ($kind === 'projects'): ?>
<div class="modal" id="m-aproj"><div class="modal-box lg">
    <div class="modal-head"><h3><i class="fa-solid fa-diagram-project"></i> <span id="ap-title">پروژه</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div id="ap-info"></div><div id="ap-part" class="mt-2"></div></div>
</div></div>
<?php endif; ?>
