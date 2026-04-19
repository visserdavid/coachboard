<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/index.php?page=formation&action=list');
}

$id               = (int) ($_POST['id'] ?? 0);
$formationService = new FormationService();
$formationService->setDefault($id);

$_SESSION['flash'] = t('formation.default_set');
redirect(APP_URL . '/index.php?page=formation&action=list');
