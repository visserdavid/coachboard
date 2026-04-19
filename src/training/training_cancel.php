<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=training');
}

require_once dirname(__DIR__, 2) . '/src/training/TrainingRepository.php';
require_once dirname(__DIR__, 2) . '/src/training/TrainingService.php';

$sessionId = (int) ($_POST['session_id'] ?? 0);
$service   = new TrainingService();
$repo      = new TrainingRepository();

$session = $repo->getSessionById($sessionId);

if ($session === null) {
    $_SESSION['flash'] = t('error.not_found');
    redirect(APP_URL . '/public/index.php?page=training');
}

$service->cancelSession($sessionId);

$_SESSION['flash'] = t('training.cancelled');
redirect(APP_URL . '/public/index.php?page=training&action=detail&id=' . $sessionId);
