<?php
/** کارت بورد پروژه. @var array $t @var string $key @var array $filters */
$overdue = !in_array($key, ['done', 'archived'], true) && $t['due_date'] && strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59')) < time();
?>
            <article class="kb-card p-<?= e($t['priority']) ?><?= $t['is_focus'] ? ' kb-focus' : '' ?>" id="kb-task-<?= (int) $t['id'] ?>" data-id="<?= (int) $t['id'] ?>" data-status="<?= e($key) ?>" data-moves="<?= e(implode(' ', $t['moves'])) ?>" <?= $t['can_move'] ? 'draggable="true"' : 'data-locked' ?>>
                <?php if ($t['is_focus']): ?><div class="kb-focus-tag" title="تسک انتخاب‌شده" aria-label="تسک انتخاب‌شده"><i class="fa-solid fa-location-crosshairs"></i></div><?php endif; ?>
                <div class="kb-card-top">
                    <a class="kb-title" href="<?= e(url('/tasks?task=' . $t['token'])) ?>" data-open-task="<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a>
                    <?php if ($t['can_move']): ?><button type="button" class="btn btn-ghost btn-icon btn-xs" data-kb-menu title="انتقال به…"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <?php else: ?><span class="faint xs" title="دسترسی جابه‌جایی این تسک در برد را ندارید"><i class="fa-solid fa-lock"></i></span><?php endif; ?>
                </div>
                <?php if ($key === 'blocked' && $t['blocked_reason']): ?><div class="kb-reason"><i class="fa-solid fa-circle-exclamation"></i> <?= e($t['blocked_reason']) ?></div><?php endif; ?>
                <div class="kb-meta">
                    <?= priority_badge($t) ?>
                    <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i> <?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $overdue ? ' (گذشته)' : '' ?></span><?php endif; ?>
                    <?php if ((int) $t['notes_count'] > 0): ?><span><i class="fa-regular fa-comment"></i> <?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                    <?php if ((int) $t['attachments_count'] > 0): ?><span><i class="fa-solid fa-paperclip"></i> <?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
                </div>
                <div class="kb-foot">
                    <?php if ($t['project_title'] && !$filters['project']): ?><span class="kb-proj"><i class="fa-solid fa-diagram-project"></i> <?= e($t['project_title']) ?></span><?php else: ?><span></span><?php endif; ?>
                    <?php if ($t['assignee_id']): $an = full_name($t, 'assignee_'); ?>
                        <span class="avatar sm" title="مسئول: <?= e($an) ?>"><?php if ($t['assignee_avatar']): ?><img src="<?= e(avatar_url($t['assignee_avatar'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initial($an)) ?><?php endif; ?></span>
                    <?php endif; ?>
                </div>
            </article>
