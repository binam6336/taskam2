<?php
/**
 * کارت بورد پروژه (v6.1؛ چیدمان v7.18 مثل کانبان دمو: عنوان + دستگیره، نشان‌ها، پای کارت با مسئول و شمارنده‌ها).
 * جابه‌جایی فقط با دکمه‌ی «جابه‌جایی» (کشیدن یا کلیک ← منوی انتقال)؛ کشیدن بقیه‌ی کارت = اسکرول افقی بورد. @var array $t @var string $key @var array $filters
 */
$overdue = !in_array($key, ['done', 'archived', 'review'], true) && $t['due_date'] && $t['due_date'] < date('Y-m-d');   // عقب‌افتاده = روزهای قبل و هنوز تحویل نشده (v7.7)
$an = $t['assignee_id'] ? full_name($t, 'assignee_') : '';
?>
            <article class="kb-card p-<?= e($t['priority']) ?><?= $t['is_focus'] ? ' kb-focus' : '' ?><?= !empty($t['is_confidential']) ? ' kb-secret' : '' ?>" id="kb-task-<?= e(pid('tasks', $t)) ?>" data-id="<?= e(pid('tasks', $t)) ?>" data-status="<?= e($key) ?>" data-moves="<?= e(implode(' ', $t['moves'])) ?>">
                <?php if ($t['is_focus']): ?><div class="kb-focus-tag" title="تسک انتخاب‌شده" aria-label="تسک انتخاب‌شده"><i class="fa-solid fa-location-crosshairs"></i></div><?php endif; ?>
                <div class="kb-card-top">
                    <a class="kb-title" href="<?= e(url('/tasks?task=' . $t['token'])) ?>" data-open-task="<?= e(pid('tasks', $t)) ?>" draggable="false"><?= e($t['title']) ?></a>
                    <?php if ($t['can_move']): ?><button type="button" class="kb-handle" draggable="true" data-kb-menu title="جابه‌جایی: بکشید یا کلیک کنید" aria-label="تغییر وضعیت «<?= e($t['title']) ?>» — بکشید یا کلیک کنید" aria-haspopup="true"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></button>
                    <?php else: ?><button type="button" class="kb-handle kb-lock" data-lock-reason="<?= e((string) ($t['lock_reason'] ?? 'دسترسی جابه‌جایی این تسک در بورد را ندارید.')) ?>" title="چرا نمی‌توانم وضعیت را تغییر دهم؟" aria-label="دلیل قفل بودن تغییر وضعیت"><i class="fa-solid fa-lock" aria-hidden="true"></i></button><?php endif; ?>
                </div>
                <?php if ($key === 'rework' && $t['blocked_reason']): ?><div class="kb-reason rework"><i class="fa-solid fa-circle-exclamation"></i> <?= e($t['blocked_reason']) ?></div><?php endif; ?>
                <div class="kb-meta">
                    <?= priority_badge($t) ?>
                    <?php if ($t['due_date']): ?><span class="kb-due<?= $overdue ? ' is-late' : '' ?>"<?= $overdue ? ' title="عقب‌افتاده"' : '' ?>><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $overdue ? '<span class="sr-only"> (عقب‌افتاده)</span>' : '' ?></span><?php endif; ?>
                    <?php if (!empty($t['subject_title'])): ?><span class="kb-lbl" data-css="--lc:<?= e((string) ($t['subject_color'] ?: '#8b5cf6')) ?>"><?= e($t['subject_title']) ?></span><?php endif; ?>
                </div>
                <div class="kb-foot">
                    <?php if ($an !== ''): ?><span class="avatar xs" title="مسئول: <?= e($an) ?>" aria-label="مسئول: <?= e($an) ?>"><?php if ($t['assignee_avatar']): ?><img loading="lazy" decoding="async" src="<?= e(avatar_url($t['assignee_avatar'])) ?>" alt=""><?php else: ?><?= e(initial($an)) ?><?php endif; ?></span><?php endif; ?>
                    <?php if (!empty($t['is_quick'])): ?><span class="kb-ic" title="ثبت سریع"><i class="fa-solid fa-bolt" aria-hidden="true"></i><span class="sr-only">ثبت سریع</span></span><?php endif; ?>
                    <?php if (!empty($t['is_confidential'])): ?><span class="kb-ic" title="محرمانه"><i class="fa-solid fa-lock" aria-hidden="true"></i><span class="sr-only">محرمانه</span></span><?php endif; ?>
                    <span class="kb-grow"></span>
                    <?php if ((int) $t['notes_count'] > 0): ?><span class="kb-ic" title="یادداشت"><i class="fa-regular fa-comment" aria-hidden="true"></i> <?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                    <?php if ((int) $t['attachments_count'] > 0): ?><span class="kb-ic" title="پیوست"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> <?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
                </div>
            </article>
