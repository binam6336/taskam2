<?php
use App\Core\Session;

$this->boot['flash'] = Session::pullFlash();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e(user_theme($currentUser ?? null)) ?>">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body>
<div class="auth-wrap"><div class="auth-card"><?= $content ?></div></div>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
