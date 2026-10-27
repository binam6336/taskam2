<?php /** @var array $tickets @var string $status */
$st = ['open' => ['باز', 'yellow'], 'answered' => ['پاسخ داده شد', 'green'], 'closed' => ['بسته', '']];
$f = static fn(string $s) => url('/admin/support') . ($s === 'all' ? '' : '?status=' . $s); ?>
<div class="page-head"><div class="tabs"><?php foreach (['all' => 'همه', 'open' => 'باز', 'answered' => 'پاسخ‌داده‌شده', 'closed' => 'بسته'] as $k => $l): ?><a class="tab <?= $status === $k ? 'active' : '' ?>" href="<?= e($f($k)) ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
<div data-live="a-support">
<?php if (!$tickets): ?><div class="card empty"><i class="fa-solid fa-inbox"></i><p>تیکتی وجود ندارد.</p></div><?php else: ?>
<div class="list"><?php foreach ($tickets as $t): ?>
    <a class="item" href="<?= e(url('/admin/support/' . $t['id'])) ?>" style="color:inherit"><div class="grow"><div class="bold">#<?= e(fa_num($t['id'])) ?> • <?= e($t['subject']) ?></div>
        <div class="item-meta"><span><i class="fa-solid fa-user"></i><?= e(full_name($t)) ?></span><span class="ltr"><?= e($t['mobile']) ?></span><span><i class="fa-regular fa-clock"></i><?= e(time_ago($t['last_reply_at'] ?: $t['created_at'])) ?></span></div></div>
        <?php if ((int) $t['unread_count'] > 0): ?><span class="badge-count"><?= e(fa_num($t['unread_count'])) ?></span><?php endif; ?>
        <span class="badge <?= e($st[$t['status']][1]) ?>"><?= e($st[$t['status']][0]) ?></span></a>
<?php endforeach; ?></div><?php endif; ?>
</div>
