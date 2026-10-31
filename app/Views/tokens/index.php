<?php
/** @var array $tokens @var ?string $newToken @var int $max */
$base = rtrim(full_url('api/v1'), '/');
?>
<div class="page-head"><h2><i class="fa-solid fa-key" style="color:var(--g-400)"></i> توکن‌های API</h2><div class="flex gap-1 wrap"><a class="btn" href="<?= e(url('/docs/authentication')) ?>"><i class="fa-solid fa-key"></i> نحوه ارسال توکن</a><a class="btn btn-primary" href="<?= e(url('/docs')) ?>"><i class="fa-solid fa-book"></i> مستندات API</a></div></div>

<?php if ($newToken): ?>
<div class="alert success" style="flex-direction:column;align-items:stretch">
    <b><i class="fa-solid fa-circle-check"></i> توکن جدید ساخته شد — همین حالا کپی کنید، دیگر نمایش داده نمی‌شود.</b>
    <div class="codebox"><pre id="new-token"><?= e($newToken) ?></pre><button class="btn btn-xs copy" data-copy="<?= e($newToken) ?>"><i class="fa-regular fa-copy"></i> کپی</button></div>
    <div class="codebox"><pre>curl -H "Authorization: Bearer <?= e($newToken) ?>" <?= e($base) ?>/me</pre></div>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-plus"></i> ساخت توکن جدید</h3><span class="badge"><?= e(fa_num(count($tokens))) ?> / <?= e(fa_num($max)) ?></span></div>
        <form method="post" action="<?= e(url('/tokens')) ?>"><?= csrf_field() ?>
            <div class="field"><label>نام توکن</label><input class="input" name="name" maxlength="100" placeholder="<?= e(ph('token_name')) ?>"></div>
            <div class="field"><label>انقضا</label><select class="input" name="expires_days"><option value="0">بدون انقضا</option><option value="30">۳۰ روز</option><option value="90" selected>۹۰ روز</option><option value="365">۱ سال</option></select></div>
            <button class="btn btn-primary"><i class="fa-solid fa-key"></i> ساخت توکن</button></form>
        <div class="callout warn mt-2 small"><i class="fa-solid fa-shield-halved"></i> توکن مانند رمز عبور است. فقط هش آن در سرور ذخیره می‌شود و بعد از ساخت قابل بازیابی نیست.</div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-terminal"></i> شروع سریع</h3></div>
        <?php $curl = "curl -X GET \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  \"" . $base . "/tasks/list?status=pending\""; ?>
        <div class="codebox"><div class="code-bar"><span class="code-lang">bash</span><button type="button" class="btn btn-xs" data-copy="<?= e($curl) ?>"><i class="fa-regular fa-copy"></i> کپی</button></div>
            <pre class="code"><?php foreach (explode("\n", $curl) as $line): ?><span class="l"><?= e($line) ?></span><?php endforeach; ?></pre></div>
        <p class="small muted">نشانی پایه: <code><?= e($base) ?></code></p></div>
</div>

<div class="card mt-2"><div class="card-head"><h3><i class="fa-solid fa-list"></i> توکن‌های شما</h3></div>
<?php if (!$tokens): ?><p class="muted">هنوز توکنی نساخته‌اید.</p><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>نام</th><th>شروع توکن</th><th>آخرین استفاده</th><th>انقضا</th><th>وضعیت</th><th></th></tr></thead><tbody>
<?php foreach ($tokens as $t): $expired = $t['expires_at'] && strtotime($t['expires_at']) < time(); ?>
    <tr><td class="bold"><?= e($t['name']) ?></td><td><code><?= e($t['token_prefix']) ?>…</code></td>
        <td class="small muted"><?= $t['last_used_at'] ? e(time_ago($t['last_used_at'])) . ' <span class="ltr xs">(' . e($t['last_ip']) . ')</span>' : 'هرگز' ?></td>
        <td class="small"><?= $t['expires_at'] ? e(jdate($t['expires_at'], false)) : '—' ?></td>
        <td><?php if ($expired): ?><span class="badge red">منقضی</span><?php elseif ((int) $t['is_active'] === 1): ?><span class="badge green">فعال</span><?php else: ?><span class="badge">غیرفعال</span><?php endif; ?></td>
        <td class="nowrap"><button class="btn btn-xs" data-post="<?= e(url('/tokens/' . $t['id'] . '/toggle')) ?>"><?= (int) $t['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button>
            <button class="btn btn-xs btn-danger" data-post="<?= e(url('/tokens/' . $t['id'] . '/delete')) ?>" data-confirm="این توکن حذف شود؟ برنامه‌هایی که از آن استفاده می‌کنند از کار می‌افتند."><i class="fa-solid fa-trash"></i></button></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
