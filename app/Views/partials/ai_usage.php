<?php
/**
 * کارت‌های مصرف هوش مصنوعی (v7.21) — پروفایل کاربر؛ همین ساختار در profile.js (renderAiu) برای به‌روزرسانی زنده ساخته می‌شود.
 * @var array $ai خروجی Quota::status + features + month
 */
$num = static fn($n) => fa_num(number_format((int) $n));
?>
<?php if (!empty($ai['blocked'])): ?><div class="aiu-alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span><?= e($ai['blocked']) ?></span></div><?php endif; ?>
<div class="aiu-grid">
<?php foreach (['daily' => 'fa-sun', 'monthly' => 'fa-calendar', 'total' => 'fa-layer-group'] as $k => $ic): $x = $ai[$k];
    $tone = $x['unlimited'] ? '' : ($x['percent'] >= 100 ? ' is-full' : ($x['percent'] >= 80 ? ' is-near' : '')); ?>
    <div class="card aiu-card<?= $tone ?>" data-aiu-kind="<?= e($k) ?>">
        <div class="aiu-head"><span class="aiu-ic"><i class="fa-solid <?= e($ic) ?>" aria-hidden="true"></i></span><h3>مصرف <?= e($x['label']) ?></h3>
            <span class="aiu-pct"><?= $x['unlimited'] ? 'نامحدود' : e(fa_num($x['percent'])) . '٪' ?></span></div>
        <div class="aiu-num"><b><?= e($num($x['used'])) ?></b> / <?= $x['unlimited'] ? 'نامحدود' : e($num($x['limit'])) ?> توکن</div>
        <?php if (!$x['unlimited']): ?>
        <div class="aiu-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $x['percent'] ?>" aria-label="درصد مصرف <?= e($x['label']) ?>"><span data-css="width:<?= (int) $x['percent'] ?>%"></span></div>
        <div class="aiu-foot small muted">باقی‌مانده: <?= e($num($x['remaining'])) ?> توکن<?php if ($x['reset']): ?> — بازنشانی: <?= e($x['reset']) ?><?php endif; ?></div>
        <?php else: ?><div class="aiu-foot small muted">برای این بازه سقفی تعیین نشده است.<?php if ($x['reset']): ?> شمارش از نو: <?= e($x['reset']) ?><?php endif; ?></div><?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<div class="card aiu-feat"><h3 class="small">مصرف <?= e($ai['month']) ?> به تفکیک بخش</h3>
<?php if ($ai['features']): ?><ul><?php foreach ($ai['features'] as $f): ?><li><span><?= e($f['feature']) ?></span><span class="muted"><?= e(fa_num($f['requests'])) ?> درخواست</span><b><?= e($num($f['tokens'])) ?> توکن</b></li><?php endforeach; ?></ul>
<?php else: ?><p class="small muted">این ماه هنوز از هوش مصنوعی استفاده نکرده‌اید.</p><?php endif; ?></div>
