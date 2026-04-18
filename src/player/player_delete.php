<?php
declare(strict_types=1);

Auth::requireRole('is_administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=squad');
}

$id     = (int) ($_POST['id'] ?? 0);
$repo   = new PlayerRepository();
$player = $repo->getPlayerById($id);

if ($player !== null) {
    $repo->deletePlayer($id);
    $_SESSION['flash'] = t('player.deleted');
} else {
    $_SESSION['flash'] = t('error.not_found');
}

redirect(APP_URL . '/public/index.php?page=squad&action=manage');
