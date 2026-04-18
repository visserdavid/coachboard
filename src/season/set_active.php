<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=season&action=list');
}

$id      = (int) ($_POST['id'] ?? 0);
$service = new SeasonService();

if ($service->setActiveSeason($id)) {
    // Refresh session season context
    $repo = new SeasonRepository();
    $_SESSION['active_season'] = $repo->getActiveSeason();
    $_SESSION['active_phases'] = $repo->getPhasesBySeason(
        (int) ($_SESSION['active_season']['id'] ?? 0)
    );
    $_SESSION['flash'] = t('season.activated');
} else {
    $_SESSION['flash'] = t('error.general');
}

redirect(APP_URL . '/public/index.php?page=season&action=list');
