<?php
/** @var array $stats @var array $days @var array $topUsers @var array $topProjects @var array $topSubjects @var array $audit @var array $chatSec */
$u = $stats['users']; $t = $stats['tasks']; $c = $stats['calls']; $m = $stats['misc'];
$this->boot['an'] = ['days' => $days, 'tasks' => $t, 'subjects' => $topSubjects];
$this->scripts[] = 'admin';
$card = static fn(string $ico, string $cls, $num, string $lbl) => '<div class="card stat"><div class="ico ' . $cls . '"><i class="fa-solid ' . $ico . '"></i></div><div><div class="num">' . e(fa_num($num)) . '</div><div class="lbl">' . e($lbl) . '</div></div></div>';
?>
<div class="grid grid-4 mb-2">
    <?= $card('fa-users', '', $u['total'], 'کاربران') ?><?= $card('fa-list-check', 'info', $t['total'], 'تسک‌ها') ?><?= $card('fa-headset', 'warn', $m['support_open'], 'تیکت پشتیبانی باز') ?><?= $card('fa-phone-volume', '', $c['total'], 'درخواست تماس') ?>
    <?= $card('fa-diagram-project', '', $m['projects'], 'پروژه‌ها') ?><?= $card('fa-comments', 'info', $m['messages'], 'پیام‌های چت') ?><?= $card('fa-message', '', $m['texts'], 'متن‌های آماده') ?><?= $card('fa-key', 'warn', $m['tokens'], 'توکن فعال') ?>
</div>
<div class="grid grid-wide mb-2"><div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> فعالیت ۱۴ روز اخیر</h3></div><div class="chart" id="ch-days"></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-pie"></i> وضعیت تسک‌ها</h3></div><div id="ch-tasks"></div></div></div>
<div class="grid grid-3 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-trophy"></i> فعال‌ترین کاربران</h3></div><div class="list"><?php foreach ($topUsers as $x): ?><div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($x), 'src' => avatar_url($x['avatar']), 'size' => 'sm']); ?><span class="grow truncate"><?= e(full_name($x)) ?></span><span class="badge green"><?= e(fa_num($x['done'])) ?>/<?= e(fa_num($x['cnt'])) ?></span></div><?php endforeach; ?></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-diagram-project"></i> پروژه‌های برتر</h3></div><div class="list"><?php foreach ($topProjects as $x): ?><div class="flex between"><span class="truncate"><?= e($x['title']) ?></span><span class="small muted"><?= e(fa_num($x['tasks'])) ?> تسک • <?= e(fa_num($x['members'])) ?> عضو</span></div><?php endforeach; ?></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-tags"></i> لیبل‌های پرتکرار</h3></div><div class="chart" id="ch-subj"></div></div>
</div>
<div class="card mb-2"><div class="card-head"><h3><i class="fa-solid fa-lock"></i> رمزگذاری پیام‌های چت</h3>
    <span class="badge <?= $chatSec['configured'] ? 'green' : 'yellow' ?>"><?= $chatSec['configured'] ? 'فعال (AES-256-GCM)' : 'غیرفعال' ?></span></div>
    <div class="grid grid-3 mb-1">
        <div class="center"><div class="num bold" style="font-size:1.4rem"><?= e(fa_num($chatSec['total'])) ?></div><div class="xs muted">کل پیام‌ها</div></div>
        <div class="center"><div class="num bold" style="font-size:1.4rem;color:var(--g-400)"><?= e(fa_num($chatSec['enc'])) ?></div><div class="xs muted">رمزشده</div></div>
        <div class="center"><div class="num bold" style="font-size:1.4rem;color:<?= $chatSec['plain'] ? 'var(--warn)' : 'var(--g-400)' ?>"><?= e(fa_num($chatSec['plain'])) ?></div><div class="xs muted">متن‌ساده (رمزگذاری‌نشده)</div></div>
    </div>
    <?php if (!$chatSec['configured']): ?><p class="small muted">در <code>.env</code> مقدار <code>ENCODE_PASSWORD</code> را (حداقل ۱۲ نویسه) بگذارید تا پیام‌های جدید رمزگذاری شوند.</p>
    <?php elseif ($chatSec['plain'] > 0 && $chatSec['isSuper']): ?><button class="btn btn-primary btn-sm" data-post="<?= e(url('/admin/chat/encrypt')) ?>" data-confirm="پیام‌های متن‌ساده‌ی موجود با رمز فعلی رمزگذاری شوند؟ (قبلش از دیتابیس بکاپ بگیرید)" data-danger="0"><i class="fa-solid fa-lock"></i> رمزگذاری پیام‌های قبلی</button>
    <?php elseif ($chatSec['plain'] > 0): ?><p class="small muted">فقط مدیر کل می‌تواند پیام‌های قبلی را رمزگذاری کند.</p>
    <?php else: ?><p class="small" style="color:var(--g-300)"><i class="fa-solid fa-circle-check"></i> همه‌ی پیام‌ها رمزگذاری شده‌اند.</p><?php endif; ?></div>

<div class="card"><div class="card-head"><h3><i class="fa-solid fa-shield-halved"></i> رویدادهای امنیتی اخیر</h3></div>
<div class="table-wrap"><table class="table"><thead><tr><th>زمان</th><th>کاربر</th><th>رویداد</th><th>IP</th></tr></thead><tbody>
<?php foreach ($audit as $a): ?><tr><td class="small"><?= e(jdate($a['created_at'])) ?></td><td><?= e($a['first_name'] ? full_name($a) : '—') ?></td><td><code><?= e($a['action']) ?></code></td><td class="ltr small"><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
