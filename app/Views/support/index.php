<?php /** @var array $tickets @var array $priorities */
$st = ['open' => ['باز', 'yellow'], 'answered' => ['پاسخ داده شد', 'green'], 'closed' => ['بسته', '']]; ?>
<div class="page-head"><h2><i class="fa-solid fa-headset" style="color:var(--g-400)"></i> پشتیبانی</h2><button class="btn btn-primary" data-modal="m-support"><i class="fa-solid fa-plus"></i> تیکت جدید</button></div>
<div data-live="s-list">
<?php if (!$tickets): ?><div class="card empty"><i class="fa-solid fa-headset"></i><p>تیکتی ندارید. هر مشکل یا پیشنهادی دارید برای مدیر ارسال کنید.</p></div><?php else: ?>
<div class="list"><?php foreach ($tickets as $t): ?>
    <a class="item" href="<?= e(url('/support/' . $t['id'])) ?>" style="color:inherit"><div class="grow"><div class="bold">#<?= e(fa_num($t['id'])) ?> • <?= e($t['subject']) ?></div>
        <div class="item-meta"><span><i class="fa-regular fa-clock"></i><?= e(time_ago($t['last_reply_at'] ?: $t['created_at'])) ?></span><span><i class="fa-regular fa-comments"></i><?= e(fa_num($t['msg_count'])) ?> پیام</span></div></div>
        <?php if ((int) $t['unread_count'] > 0): ?><span class="badge-count"><?= e(fa_num($t['unread_count'])) ?></span><?php endif; ?>
        <span class="badge <?= e($st[$t['status']][1]) ?>"><?= e($st[$t['status']][0]) ?></span></a>
<?php endforeach; ?></div><?php endif; ?>
</div>
<div class="modal" id="m-support"><div class="modal-box"><form method="post" action="<?= e(url('/support')) ?>"><?= csrf_field() ?>
    <div class="modal-head"><h3><i class="fa-solid fa-headset"></i> تیکت پشتیبانی</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div class="field"><label>موضوع *</label><input class="input" name="subject" maxlength="255" required></div>
        <div class="field"><label>اولویت</label><select class="input" name="priority"><?php foreach ($priorities as $k => $v): ?><option value="<?= e($k) ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>پیام *</label><textarea class="input" name="message" rows="5" maxlength="5000" required></textarea></div></div>
    <div class="modal-foot"><button class="btn btn-primary">ارسال</button></div></form></div></div>
