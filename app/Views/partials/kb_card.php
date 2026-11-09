<?php
/** کارت بورد پروژه (v6.1). جابه‌جایی فقط با دکمه‌ی «جابه‌جایی» (کشیدن یا کلیک ← منوی انتقال)؛ کشیدن بقیه‌ی کارت = اسکرول افقی بورد. @var array $t @var string $key @var array $filters */
$overdue = !in_array($key, ['done', 'archived'], true) && $t['due_date'] && $t['due_date'] < date('Y-m-d');   // عقب‌افتاده = روزهای قبل
?>
            <article class="kb-card p-<?= e($t['priority']) ?><?= $t['is_focus'] ? ' kb-focus' : '' ?><?= !empty($t['is_confidential']) ? ' kb-secret' : '' ?>" id="kb-task-<?= e(pid('tasks', $t)) ?>" data-id="<?= e(pid('tasks', $t)) ?>" data-status="<?= e($key) ?>" data-moves="<?= e(implode(' ', $t['moves'])) ?>">
                <?php if ($t['is_focus']): ?><div class="kb-focus-tag" title="تسک انتخاب‌شده" aria-label="تسک انتخاب‌شده"><i class="fa-solid fa-location-crosshairs"></i></div><?php endif; ?>
                <div class="kb-card-top">
                    <?php if ($t['can_move']): ?><button type="button" class="kb-handle" draggable="true" data-kb-menu title="جابه‌جایی: بکشید یا کلیک کنید" aria-label="تغییر وضعیت «<?= e($t['title']) ?>»" aria-haspopup="true"><i class="fa-solid fa-up-down-left-right"></i></button>
                    <?php else: ?><button type="button" class="kb-handle kb-lock" data-lock-reason="<?= e((string) ($t['lock_reason'] ?? 'دسترسی جابه‌جایی این تسک در بورد را ندارید.')) ?>" title="چرا نمی‌توانم وضعیت را تغییر دهم؟" aria-label="دلیل قفل بودن تغییر وضعیت"><i class="fa-solid fa-lock"></i></button><?php endif; ?>
                    <a class="kb-title" href="<?= e(url('/tasks?task=' . $t['token'])) ?>" data-open-task="<?= e(pid('tasks', $t)) ?>" draggable="false"><?php if (!empty($t['is_confidential'])): ?><i class="fa-solid fa-user-secret kb-secret-ic" title="محرمانه" aria-label="محرمانه"></i> <?php endif; ?><?= e($t['title']) ?></a>
                </div>
                <?php if ($key === 'rework' && $t['blocked_reason']): ?><div class="kb-reason rework"><i class="fa-solid fa-circle-exclamation"></i> <?= e($t['blocked_reason']) ?></div><?php endif; ?>
                <div class="kb-meta">
                    <?= priority_badge($t) ?>
                    <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i> <?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $overdue ? ' (عقب‌افتاده)' : '' ?></span><?php endif; ?>
                    <?php if ((int) $t['notes_count'] > 0): ?><span title="یادداشت"><i class="fa-regular fa-comment"></i> <?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                    <?php if ((int) $t['attachments_count'] > 0): ?><span title="پیوست"><i class="fa-solid fa-paperclip"></i> <?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
                </div>
                <?php if ($t['assignee_id'] || !empty($t['assignee_group_id'])): ?>
                <div class="kb-foot">
                    <?php if ($t['assignee_id']): $an = full_name($t, 'assignee_'); ?>
                        <span class="kb-who"><span class="avatar xs"><?php if ($t['assignee_avatar']): ?><img src="<?= e(avatar_url($t['assignee_avatar'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initial($an)) ?><?php endif; ?></span><span class="truncate"><?= e($an) ?></span></span>
                    <?php else: // تسک گروهی (v5.10) ?>
                        <span class="kb-grp" title="مسئول: گروه <?= e((string) $t['group_title']) ?>"><i class="fa-solid fa-people-group"></i> <?= e((string) $t['group_title']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </article>
