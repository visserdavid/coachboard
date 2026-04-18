<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=training');
}

$teamId = (int) ($_POST['team_id'] ?? 0);
$date   = trim($_POST['date'] ?? '');

if ($teamId === 0 || $date === '') {
    $_SESSION['flash'] = t('error.general');
    redirect(APP_URL . '/public/index.php?page=training');
}

try {
    $service = new SeasonService();
    $service->addManualTrainingSession($teamId, $date);
    $_SESSION['flash'] = t('season.training_added');
} catch (RuntimeException $e) {
    $_SESSION['flash'] = $e->getMessage();
}

redirect(APP_URL . '/public/index.php?page=training');
