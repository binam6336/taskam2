<?php /** @var array $group */ use App\Services\DocsHelper as D; ?>
<article class="dx-article">
<h1><i class="fa-solid <?= e($group['icon']) ?>" style="color:var(--g-400)"></i> <?= e($group['title']) ?></h1>
<p class="muted"><?= e($group['desc']) ?></p>
<table class="dx-table"><thead><tr><th>#</th><th>متد</th><th>مسیر</th><th>توضیحات</th></tr></thead><tbody>
<?php foreach ($group['endpoints'] as $i => $ep): ?>
    <tr><td><?= e(fa_num($i + 1)) ?></td><td><span class="dx-method m-<?= strtolower($ep['method']) ?>"><?= e($ep['method']) ?></span></td>
        <td><a href="<?= e(D::url($group['id'], $ep)) ?>"><code><?= e($ep['path']) ?></code></a></td><td><?= e($ep['summary']) ?></td></tr>
<?php endforeach; ?></tbody></table>
</article>
