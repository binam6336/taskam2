<?php /** @var array $ticket @var array $messages */
$closed = $ticket['status'] === 'closed'; ?>
<div class="page-head"><div><a class="small muted" href="<?= e(url('/support')) ?>"><i class="fa-solid fa-arrow-right"></i> تیکت‌ها</a><h2 class="mt-1">#<?= e(fa_num($ticket['id'])) ?> • <?= e($ticket['subject']) ?></h2></div>
    <div class="flex gap-1"><?php if ($closed): ?><button class="btn" data-post="<?= e(url('/support/' . $ticket['id'] . '/reopen')) ?>">بازکردن دوباره</button><?php else: ?><button class="btn" data-post="<?= e(url('/support/' . $ticket['id'] . '/close')) ?>" data-confirm="تیکت بسته شود؟">بستن</button><?php endif; ?>
        <button class="btn btn-danger btn-icon" data-post="<?= e(url('/support/' . $ticket['id'] . '/delete')) ?>" data-confirm="تیکت حذف شود؟" data-after="redirect:/support"><i class="fa-solid fa-trash"></i></button></div></div>
<div class="card"><div data-live="s-msgs"><?php foreach ($messages as $m): $admin = $m['sender_role'] === 'admin'; ?>
    <div class="note" style="<?= $admin ? 'border-color:rgba(34,197,94,.4);background:var(--ok-bg)' : '' ?>"><?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?>
        <div class="grow"><div class="flex between"><b class="small"><?= $admin ? 'پشتیبانی' : e(full_name($m)) ?></b><span class="xs faint"><?= e(jdate($m['created_at'])) ?></span></div><div class="note-body"><?= e($m['message']) ?></div></div></div>
<?php endforeach; ?></div>
<?php if (!$closed): ?><form method="post" action="<?= e(url('/support/' . $ticket['id'] . '/reply')) ?>" class="mt-2"><?= csrf_field() ?><textarea class="input" name="message" rows="3" placeholder="پاسخ شما…" required></textarea><button class="btn btn-primary mt-1"><i class="fa-solid fa-paper-plane"></i> ارسال پاسخ</button></form>
<?php else: ?><div class="alert info mt-2">این تیکت بسته شده است.</div><?php endif; ?></div>
