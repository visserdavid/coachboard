<?php
declare(strict_types=1);

Auth::requireRole('is_administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=squad&action=manage');
}

$id   = (int) ($_POST['id'] ?? 0);
$repo = new PlayerRepository();

$repo->restorePlayer($id);
$_SESSION['flash'] = t('player.restored');

redirect(APP_URL . '/public/index.php?page=squad&action=manage');
