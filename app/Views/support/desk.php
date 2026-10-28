<?php
/**
 * میز تیکت (مشترک کاربر و ادمین): فهرست تیکت‌ها کنار گفتگوی تیکت بازشده.
 * @var bool $admin @var string $base @var array $list @var array $params @var ?array $ticket @var array $messages
 * @var int $me @var array $texts @var array $priorities @var ?array $counts
 */
use App\Models\SupportModel;
use App\Services\SupportDesk;

$this->scripts[] = 'support';
$this->boot['support'] = ['texts' => $texts];
$qs = static fn(array $o = []) => SupportDesk::query($params, $o);
$tid = $ticket ? (int) $ticket['id'] : 0;
$closed = $ticket && $ticket['status'] === 'closed';
$canWrite = $ticket && ($admin || !$closed);
$imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$badgeHtml = static function (array $t) use ($admin): string {
    if ($admin) {
        // پنل ادمین: دسته‌ی تیکت (جدید / منتظر پاسخ / پاسخ داده شده / بسته شده) + «در حال انجام»
        [, $label, $tone] = SupportModel::adminCategory($t);
        return '<span class="badge ' . e($tone) . '">' . e($label) . '</span>' . ($t['status'] === 'in_progress' ? '<span class="tbadge-wait user">در حال انجام</span>' : '');
    }
    $b = SupportModel::badges($t);
    $h = '<span class="badge ' . e($b['state'][1]) . '">' . e($b['state'][0]) . '</span>';
    if ($b['wait']) {
        $h .= '<span class="tbadge-wait ' . e($b['wait'][1]) . '">' . e($b['wait'][0]) . '</span>';
    }
    return $h;
};
?>
<div class="desk <?= $ticket ? 'in-conv' : '' ?>" id="desk">
    <!-- ───── فهرست تیکت‌ها ───── -->
    <aside class="desk-side" id="desk-side" aria-label="تاریخچه تیکت‌ها">
        <div class="desk-side-head">
            <div class="flex between gap-1">
                <div class="grow" style="min-width:0"><h3 class="desk-h">تاریخچه تیکت‌ها</h3>
                    <div class="xs muted"><?= $admin ? 'تیکت‌های ثبت‌شده‌ی همه‌ی کاربران' : 'تیکت‌های ثبت‌شده‌ی شما' ?></div></div>
                <span class="badge" data-live="desk-count"><?= e(fa_num($list['total'])) ?> تیکت</span>
                <?php if (!$admin): ?><button class="btn btn-primary btn-sm" data-modal="m-support" type="button"><i class="fa-solid fa-plus"></i> <span class="hide-xs">تیکت جدید</span></button><?php endif; ?>
                <?php if ($ticket): ?><button type="button" class="btn btn-ghost btn-icon btn-sm desk-side-close" data-desk-list="close" aria-label="بستن فهرست"><i class="fa-solid fa-xmark"></i></button><?php endif; ?>
            </div>
            <form class="desk-search search" method="get" action="<?= e(url($base)) ?>" role="search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input class="input" type="search" name="q" value="<?= e($params['q']) ?>" placeholder="<?= $admin ? 'جستجو: موضوع، نام، موبایل یا شماره تیکت…' : 'جستجو: موضوع یا شماره تیکت…' ?>" autocomplete="off">
                <?php if ($params['status'] !== $params['default']): ?><input type="hidden" name="status" value="<?= e($params['status']) ?>"><?php endif; ?>
                <?php if ($params['per'] !== 20): ?><input type="hidden" name="per" value="<?= e((string) $params['per']) ?>"><?php endif; ?>
            </form>
            <nav class="desk-filters" aria-label="فیلتر وضعیت">
                <?php foreach (SupportModel::filters($admin) as $k => [$label]): ?>
                <a class="chip <?= $params['status'] === $k ? 'active' : '' ?>" href="<?= e(url($base) . $qs(['status' => $k, 'page' => 1])) ?>"><?= e($label) ?><?php if ($counts !== null): ?> <span class="chip-n" data-live="desk-n-<?= e($k) ?>"><?= e(fa_num($counts[$k] ?? 0)) ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="desk-list" data-live="desk-list">
        <?php if (!$list['rows']): ?>
            <div class="desk-none"><i class="fa-solid fa-inbox"></i><p><?= $params['q'] !== '' || $params['status'] !== 'all' ? ($params['q'] === '' && $params['status'] === 'new' ? 'تیکت جدیدی وجود ندارد؛ همه‌ی تیکت‌ها پاسخ گرفته‌اند.' : 'تیکتی با این فیلتر پیدا نشد.') : ($admin ? 'هنوز تیکتی ثبت نشده است.' : 'تیکتی ندارید. هر سؤال، مشکل یا پیشنهادی دارید برای پشتیبانی بفرستید.') ?></p></div>
        <?php endif; ?>
        <?php foreach ($list['rows'] as $t): $on = (int) $t['id'] === $tid; ?>
            <a class="tcard <?= $on ? 'active' : '' ?> <?= $t['status'] === 'closed' ? 'closed' : '' ?>" href="<?= e(url($base . '/' . $t['id']) . $qs()) ?>" <?= $on ? 'aria-current="true"' : '' ?>>
                <div class="tcard-top">
                    <span class="tcard-no"><i class="fa-solid fa-ticket"></i> شماره تیکت <b>#<?= e(fa_num($t['id'])) ?></b></span>
                    <span class="tcard-badges"><?= $badgeHtml($t) ?></span>
                </div>
                <div class="tcard-title"><span class="truncate"><?= e($t['subject']) ?></span><?php if ((int) $t['unread_count'] > 0): ?><span class="badge-count" title="پیام خوانده‌نشده"><?= e(fa_num($t['unread_count'])) ?></span><?php endif; ?></div>
                <?php if ($admin): ?><div class="tcard-user"><i class="fa-solid fa-user"></i><span class="truncate"><?= e(full_name($t)) ?></span><span class="ltr faint"><?= e($t['mobile']) ?></span></div><?php endif; ?>
                <div class="tcard-meta">
                    <span><i class="fa-solid fa-flag"></i>اولویت: <b><?= e($priorities[$t['priority']] ?? '—') ?></b></span>
                    <span title="<?= e(jdate($t['created_at'])) ?>"><i class="fa-regular fa-calendar"></i>تاریخ ثبت: <b><?= e(time_ago($t['created_at'])) ?></b></span>
                    <span><i class="fa-regular fa-comments"></i><?= e(fa_num($t['msg_count'])) ?> پیام</span>
                    <span title="<?= e(jdate($t['last_reply_at'] ?: $t['created_at'])) ?>"><i class="fa-solid fa-reply"></i>آخرین پاسخ: <b><?= e(time_ago($t['last_reply_at'] ?: $t['created_at'])) ?></b></span>
                </div>
                <span class="tcard-btn"><?= $on ? '<i class="fa-solid fa-eye"></i> در حال مشاهده' : '<i class="fa-regular fa-eye"></i> مشاهده تیکت' ?></span>
            </a>
        <?php endforeach; ?>
        </div>

        <div class="desk-side-foot">
            <span class="xs muted" data-live="desk-total">مجموع رکوردها: <b><?= e(fa_num($list['total'])) ?></b></span>
            <?php if ($list['pages'] > 1): ?>
            <span class="desk-pager">
                <a class="btn btn-ghost btn-icon btn-sm <?= $list['page'] <= 1 ? 'disabled' : '' ?>" href="<?= e(url($base . ($tid ? '/' . $tid : '')) . $qs(['page' => max(1, $list['page'] - 1)])) ?>" aria-label="صفحه‌ی قبل"><i class="fa-solid fa-chevron-right"></i></a>
                <span class="xs"><?= e(fa_num($list['page'])) ?> / <?= e(fa_num($list['pages'])) ?></span>
                <a class="btn btn-ghost btn-icon btn-sm <?= $list['page'] >= $list['pages'] ? 'disabled' : '' ?>" href="<?= e(url($base . ($tid ? '/' . $tid : '')) . $qs(['page' => min($list['pages'], $list['page'] + 1)])) ?>" aria-label="صفحه‌ی بعد"><i class="fa-solid fa-chevron-left"></i></a>
            </span>
            <?php endif; ?>
            <form class="desk-per" method="get" action="<?= e(url($base . ($tid ? '/' . $tid : ''))) ?>">
                <?php foreach (['status' => $params['status'] !== $params['default'] ? $params['status'] : '', 'q' => $params['q']] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
                <label class="xs muted" for="desk-per">تعداد در صفحه:</label>
                <select class="input input-sm" id="desk-per" name="per"><?php foreach (SupportModel::PER_PAGE as $n): ?><option value="<?= $n ?>" <?= $params['per'] === $n ? 'selected' : '' ?>><?= e(fa_num($n)) ?></option><?php endforeach; ?></select>
            </form>
        </div>
    </aside>

    <?php if ($ticket): ?><div class="desk-scrim" data-desk-list="close" hidden></div><?php endif; ?>
    <!-- ───── گفتگوی تیکت ───── -->
    <section class="desk-main">
    <?php if (!$ticket): ?>
        <div class="desk-empty">
            <div class="desk-empty-ico"><i class="fa-solid fa-headset"></i></div>
            <h3>یک تیکت را انتخاب کنید</h3>
            <p class="muted small"><?= $admin ? 'برای دیدن گفتگو و پاسخ دادن، روی «مشاهده تیکت» در فهرست کلیک کنید.' : 'گفتگوی هر تیکت با پشتیبانی اینجا نمایش داده می‌شود. برای سؤال یا مشکل تازه یک تیکت جدید ثبت کنید.' ?></p>
            <?php if (!$admin): ?><button class="btn btn-primary mt-1" data-modal="m-support" type="button"><i class="fa-solid fa-plus"></i> تیکت جدید</button><?php endif; ?>
        </div>
    <?php else: ?>
        <header class="desk-head" data-live="desk-head">
            <button type="button" class="btn btn-icon btn-sm desk-list-toggle" data-desk-list="toggle" aria-controls="desk-side" title="فهرست تیکت‌ها"><i class="fa-solid fa-list-ul"></i></button>
            <a class="btn btn-ghost btn-icon desk-back" href="<?= e(url($base) . $qs()) ?>" aria-label="بازگشت به فهرست"><i class="fa-solid fa-arrow-right"></i></a>
            <span class="desk-head-ico"><i class="fa-solid <?= $admin ? 'fa-id-card' : 'fa-headset' ?>"></i></span>
            <div class="grow desk-head-txt">
                <div class="desk-title"><?php if ($admin): ?><b><?= e(full_name($ticket)) ?></b> <span class="muted">(<?= e($ticket['subject']) ?>)</span><?php else: ?><b><?= e($ticket['subject']) ?></b><?php endif; ?></div>
                <div class="desk-sub">
                    <span>#<?= e(fa_num($tid)) ?></span>
                    <?php if ($admin): ?><span class="ltr"><?= e($ticket['mobile']) ?></span><?php endif; ?>
                    <span>اولویت <?= e($priorities[$ticket['priority']] ?? '—') ?></span>
                    <span class="hide-sm">ثبت: <?= e(jdate($ticket['created_at'])) ?></span>
                    <span class="tcard-badges"><?= $badgeHtml($ticket) ?></span>
                </div>
            </div>
            <div class="desk-actions">
                <?php if ($admin && !$closed): $ip = $ticket['status'] === 'in_progress'; ?>
                    <button type="button" class="btn btn-sm desk-check <?= $ip ? 'on' : '' ?>" data-post="<?= e(url($base . '/' . $tid . '/status')) ?>" data-data='{"status":"<?= $ip ? 'open' : 'in_progress' ?>"}' aria-pressed="<?= $ip ? 'true' : 'false' ?>" title="علامت «در حال انجام» برای این تیکت"><i class="fa-<?= $ip ? 'solid fa-square-check' : 'regular fa-square' ?>"></i> <span class="hide-sm">در حال انجام</span></button>
                <?php endif; ?>
                <?php if ($closed): ?>
                    <button type="button" class="btn btn-sm desk-reopen" data-after="redirect:<?= e($base . '/' . $tid . $qs()) ?>" data-post="<?= e(url($admin ? $base . '/' . $tid . '/status' : $base . '/' . $tid . '/reopen')) ?>" <?= $admin ? 'data-data=\'{"status":"open"}\'' : '' ?>><i class="fa-solid fa-lock-open"></i> <span class="hide-sm">باز کردن تیکت</span></button>
                <?php else: ?>
                    <button type="button" class="btn btn-sm desk-close" data-after="redirect:<?= e($base . '/' . $tid . $qs()) ?>" data-post="<?= e(url($admin ? $base . '/' . $tid . '/status' : $base . '/' . $tid . '/close')) ?>" <?= $admin ? 'data-data=\'{"status":"closed"}\'' : '' ?> data-confirm="تیکت بسته شود؟" data-danger="0" data-ok="بستن تیکت"><i class="fa-solid fa-lock"></i> <span class="hide-sm">بستن تیکت</span></button>
                <?php endif; ?>
                <?php if ($canWrite): ?><button type="button" class="btn btn-icon btn-sm" id="desk-texts-btn" aria-haspopup="true" title="پاسخ‌های آماده (متن‌های آماده)"><i class="fa-solid fa-keyboard"></i></button><?php endif; ?>
                <button type="button" class="btn btn-icon btn-sm hide-sm" id="desk-help-btn" aria-haspopup="true" title="راهنما"><i class="fa-solid fa-question"></i></button>
                <button type="button" class="btn btn-icon btn-sm" id="desk-refresh" title="به‌روزرسانی"><i class="fa-solid fa-rotate-right"></i></button>
                <button type="button" class="btn btn-icon btn-sm btn-danger" data-post="<?= e(url($base . '/' . $tid . '/delete')) ?>" data-confirm="تیکت و همه‌ی پیام‌ها و فایل‌هایش حذف شود؟" data-after="redirect:<?= e($base . $qs()) ?>" title="حذف تیکت"><i class="fa-solid fa-trash"></i></button>
            </div>
        </header>

        <div class="desk-msgs" id="desk-msgs" data-live="desk-msgs">
        <?php $day = ''; foreach ($messages as $m):
            $mine = (int) $m['sender_id'] === $me;
            $staff = $m['sender_role'] === 'admin';
            $d = jdate($m['created_at'], false, true);
            if ($d !== $day): $day = $d; ?><div class="day-sep"><?= e($d) ?></div><?php endif; ?>
            <div class="dmsg <?= $mine ? 'mine' : '' ?> <?= $staff ? 'staff' : '' ?>">
                <?php $this->partial('avatar', ['name' => full_name($m), 'src' => avatar_url($m['avatar']), 'size' => 'sm']); ?>
                <div class="dmsg-bubble">
                    <div class="dmsg-head"><b><?= e($staff && !$admin ? 'پشتیبانی • ' . $m['first_name'] : full_name($m)) ?></b><?php if ($staff && $admin): ?><span class="dmsg-tag">کارشناس</span><?php endif; ?><span class="faint" title="<?= e(jdate($m['created_at'])) ?>"><?= e(time_ago($m['created_at'])) ?></span></div>
                    <?php if ((string) $m['message'] !== ''): ?><div class="dmsg-body"><?= e($m['message']) ?></div><?php endif; ?>
                    <?php if (!empty($m['attachment'])): $ext = strtolower(pathinfo((string) $m['attachment'], PATHINFO_EXTENSION)); $fu = url('/files/support_files/' . $m['attachment']); ?>
                        <?php if (in_array($ext, $imgExt, true)): ?>
                            <a class="dmsg-img" href="<?= e($fu) ?>" target="_blank" rel="noopener"><img src="<?= e($fu) ?>" alt="<?= e($m['attachment_name']) ?>" loading="lazy"></a>
                        <?php endif; ?>
                        <a class="dmsg-file" href="<?= e($fu . '?dl=1') ?>"><i class="fa-solid <?= e(file_icon($ext)) ?>"></i><span class="truncate"><?= e($m['attachment_name'] ?: $m['attachment']) ?></span><small class="faint"><?= e(human_size((int) $m['attachment_size'])) ?></small><i class="fa-solid fa-download xs"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($closed): ?><div class="desk-closed-note"><i class="fa-solid fa-lock"></i> این تیکت <?= $ticket['closed_at'] ? e(time_ago($ticket['closed_at'])) . ' ' : '' ?>بسته شده است.</div><?php endif; ?>
        </div>

        <?php if ($canWrite): ?>
        <form class="desk-compose" id="desk-form" method="post" action="<?= e(url($base . '/' . $tid . '/reply')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="desk-hint">
                <span><i class="fa-regular fa-keyboard"></i> <span class="hide-sm">برای ارسال</span> <kbd>Ctrl</kbd>+<kbd>Enter</kbd></span>
                <span class="desk-file-chip" id="desk-file-chip" hidden><i class="fa-solid fa-paperclip"></i><span class="truncate" id="desk-file-name"></span><button type="button" class="desk-file-x" id="desk-file-x" aria-label="حذف پیوست"><i class="fa-solid fa-xmark"></i></button></span>
                <?php if ($admin && $closed): ?><span class="warn-txt"><i class="fa-solid fa-circle-info"></i> ارسال پاسخ، تیکت را دوباره باز می‌کند.</span><?php endif; ?>
            </div>
            <div class="desk-input">
                <label class="btn btn-icon desk-attach" title="پیوست فایل (حداکثر ۱۰ مگابایت)"><i class="fa-solid fa-paperclip"></i><input type="file" name="attachment" id="desk-file" hidden></label>
                <textarea class="input" name="message" id="desk-text" rows="1" maxlength="<?= SupportModel::MSG_MAX ?>" placeholder="<?= $admin ? 'پاسخ خود را بنویسید…' : 'متن پیام خود را بنویسید…' ?>"></textarea>
                <button class="desk-send" type="submit" aria-label="ارسال"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </form>
        <?php else: ?>
        <div class="desk-locked"><span><i class="fa-solid fa-lock"></i> این تیکت بسته شده است. برای ادامه‌ی گفتگو آن را دوباره باز کنید.</span>
            <button type="button" class="btn btn-primary btn-sm" data-after="redirect:<?= e($base . '/' . $tid . $qs()) ?>" data-post="<?= e(url($base . '/' . $tid . '/reopen')) ?>"><i class="fa-solid fa-lock-open"></i> باز کردن تیکت</button></div>
        <?php endif; ?>

        <div class="desk-pop" id="desk-texts" hidden>
            <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input class="input input-sm" id="desk-texts-q" placeholder="جستجو در متن‌های آماده…" autocomplete="off"></div>
            <div class="desk-pop-list" id="desk-texts-list"></div>
            <a class="xs" href="<?= e(url('/texts')) ?>"><i class="fa-solid fa-gear"></i> مدیریت متن‌های آماده</a>
        </div>
        <div class="desk-pop" id="desk-help" hidden>
            <b class="small">راهنما</b>
            <ul class="small muted">
                <li><kbd>Ctrl</kbd>+<kbd>Enter</kbd>: ارسال پیام</li>
                <li><i class="fa-solid fa-paperclip"></i> پیوست: تصویر، PDF، ورد، اکسل، ZIP (حداکثر ۱۰ مگابایت)</li>
                <li><i class="fa-solid fa-keyboard"></i> درج سریع از «متن‌های آماده»</li>
                <?php if ($admin): ?><li><i class="fa-regular fa-square-check"></i> «در حال انجام»: به کاربر نشان می‌دهد تیکت در دست بررسی است</li><?php endif; ?>
                <li>گفتگو هر چند ثانیه خودکار به‌روز می‌شود.</li>
            </ul>
        </div>
    <?php endif; ?>
    </section>
</div>

<?php if (!$admin): ?>
<div class="modal" id="m-support"><div class="modal-box"><form method="post" action="<?= e(url('/support')) ?>" enctype="multipart/form-data" data-ajax><?= csrf_field() ?>
    <div class="modal-head"><h3><i class="fa-solid fa-headset"></i> تیکت پشتیبانی جدید</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="field"><label>موضوع *</label><input class="input" name="subject" maxlength="255" required placeholder="مثلاً: مشکل در آپلود فایل"></div>
        <div class="field"><label>اولویت</label><select class="input" name="priority"><?php foreach ($priorities as $k => $v): ?><option value="<?= e($k) ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>پیام *</label><textarea class="input" name="message" rows="5" maxlength="<?= SupportModel::MSG_MAX ?>" required placeholder="مشکل یا درخواست خود را با جزئیات بنویسید…"></textarea></div>
        <div class="field"><label>پیوست (اختیاری)</label><input class="input" type="file" name="attachment"><div class="hint">تصویر، PDF، ورد، اکسل یا ZIP — حداکثر ۱۰ مگابایت</div></div>
    </div>
    <div class="modal-foot"><button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ثبت تیکت</button></div></form></div></div>
<?php endif; ?>
