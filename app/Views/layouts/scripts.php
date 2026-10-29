<?php if ($errs = \App\Core\ErrorText::clientMap()) { $this->boot['errs'] = $errs; } // متن خطاهای ویرایش‌شده برای TK.toast ?>
<?php if ($phs = \App\Core\PlaceholderText::overrides()) { $this->boot['ph'] = $phs; } // placeholderهای ویرایش‌شده برای TK.ph ?>
<script type="application/json" id="boot"><?= json_html($this->boot) ?></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/widgets.js')) ?>"></script>
<?php foreach ($this->scripts as $s): ?>
<script src="<?= e(asset('js/modules/' . $s . '.js')) ?>"></script>
<?php endforeach; ?>
