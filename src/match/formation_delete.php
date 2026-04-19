<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/index.php?page=formation&action=list');
}

$id               = (int) ($_POST['id'] ?? 0);
$formationService = new FormationService();
$check            = $formationService->isDeleteable($id);

if (!$check['ok']) {
    $_SESSION['flash'] = match($check['reason']) {
        'default' => t('formation.delete_default'),
        'in_use'  => t('formation.delete_in_use'),
        default   => t('error.general'),
    };
    redirect(APP_URL . '/index.php?page=formation&action=list');
}

$formationService->deleteFormation($id);
$_SESSION['flash'] = t('formation.deleted');
redirect(APP_URL . '/index.php?page=formation&action=list');
